#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
ENV_TEMPLATE="${ROOT_DIR}/.env.example"
ENV_LIB="${ROOT_DIR}/scripts/lib/env.sh"
PLUGIN_SLUG="ai-content-forge"
CONTAINER_PLUGIN_REPO="/workspace/ai-content-forge"
ENV_KEYS=(
	SITE_PORT
	PMA_PORT
	MARIADB_DATABASE
	MARIADB_USER
	MARIADB_PASSWORD
	MARIADB_ROOT_PASSWORD
	WORDPRESS_DB_HOST
	WORDPRESS_DB_NAME
	WORDPRESS_DB_USER
	WORDPRESS_DB_PASSWORD
	PMA_HOST
	PMA_USER
	PMA_PASSWORD
	WP_ADMIN_USERNAME
	WP_ADMIN_PASSWORD
	WP_ADMIN_EMAIL
	WP_SITE_TITLE
	WP_BLOG_DESCRIPTION
	OLLAMA_PROXY_PORT
	OLLAMA_HOST_TARGET
)

cd "${ROOT_DIR}"

if [[ ! -f "${ENV_LIB}" ]]; then
	echo "Missing env helper library: ${ENV_LIB}" >&2
	exit 1
fi

if [[ ! -f "${ENV_TEMPLATE}" ]]; then
	echo "Missing env template: ${ENV_TEMPLATE}" >&2
	exit 1
fi

# shellcheck source=./lib/env.sh
source "${ENV_LIB}"

if [[ ! -t 0 ]]; then
	echo "scripts/docker-setup.sh requires an interactive terminal." >&2
	exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
	echo "Docker is required but was not found in PATH." >&2
	exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
	echo "Docker Compose v2 ('docker compose') is required." >&2
	exit 1
fi

prompt_var() {
	local key="$1"
	local label="$2"
	local current_default="${!key:-}"
	local input=""

	read -r -p "${label} [${current_default}]: " input
	printf -v "${key}" '%s' "${input:-${current_default}}"
}

generate_secure_secret() {
	if command -v openssl >/dev/null 2>&1; then
		openssl rand -base64 36 | tr -d '\n' | tr '/+' 'AZ' | cut -c1-32
	else
		head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | cut -c1-32
	fi
}

generate_safe_identifier() {
	local prefix="$1"
	local suffix=""

	if command -v openssl >/dev/null 2>&1; then
		suffix="$(openssl rand -hex 4 | tr '[:upper:]' '[:lower:]')"
	else
		suffix="$(head -c 16 /dev/urandom | base64 | tr -dc 'a-z0-9' | cut -c1-8)"
	fi

	printf '%s_%s' "${prefix}" "${suffix}"
}

set_default_if_blank() {
	local key="$1"
	local value="$2"

	if [[ -z "${!key:-}" ]]; then
		printf -v "${key}" '%s' "${value}"
	fi
}

bootstrap_defaults() {
	set_default_if_blank "SITE_PORT" "8080"
	set_default_if_blank "PMA_PORT" "8081"

	set_default_if_blank "MARIADB_DATABASE" "$(generate_safe_identifier "wordpress")"
	set_default_if_blank "MARIADB_USER" "$(generate_safe_identifier "wpuser")"
	set_default_if_blank "MARIADB_PASSWORD" "$(generate_secure_secret)"
	set_default_if_blank "MARIADB_ROOT_PASSWORD" "$(generate_secure_secret)"

	set_default_if_blank "WORDPRESS_DB_HOST" "db"
	set_default_if_blank "WORDPRESS_DB_NAME" "${MARIADB_DATABASE}"
	set_default_if_blank "WORDPRESS_DB_USER" "${MARIADB_USER}"
	set_default_if_blank "WORDPRESS_DB_PASSWORD" "${MARIADB_PASSWORD}"

	set_default_if_blank "PMA_HOST" "db"
	set_default_if_blank "PMA_USER" "${MARIADB_USER}"
	set_default_if_blank "PMA_PASSWORD" "${MARIADB_PASSWORD}"

	set_default_if_blank "WP_ADMIN_USERNAME" "admin"
	set_default_if_blank "WP_ADMIN_PASSWORD" "$(generate_secure_secret)"
	set_default_if_blank "WP_ADMIN_EMAIL" "admin@example.test"
	set_default_if_blank "WP_SITE_TITLE" "AI Content Forge Development"
	set_default_if_blank "WP_BLOG_DESCRIPTION" "WordPress development environment"

	set_default_if_blank "OLLAMA_PROXY_PORT" "11435"
	set_default_if_blank "OLLAMA_HOST_TARGET" "127.0.0.1:11434"
}

write_env_file() {
	local key=""

	env_ensure_file "${ENV_FILE}" "${ENV_TEMPLATE}"

	for key in "${ENV_KEYS[@]}"; do
		env_set "${ENV_FILE}" "${key}" "${!key:-}"
	done
}

resolve_latest_plugin_zip() {
	local candidate_path=""
	local candidate_file=""
	local best_file=""
	local candidate_version=""
	local best_version=""

	shopt -s nullglob
	for candidate_path in "${ROOT_DIR}/${PLUGIN_SLUG}-v"*.zip; do
		candidate_file="$(basename "${candidate_path}")"
		candidate_version="${candidate_file#${PLUGIN_SLUG}-v}"
		candidate_version="${candidate_version%.zip}"

		if [[ -z "${best_file}" ]] || semver_gte "${candidate_version}" "${best_version}"; then
			best_file="${candidate_file}"
			best_version="${candidate_version}"
		fi
	done
	shopt -u nullglob

	printf '%s' "${best_file}"
}

install_plugin_zip() {
	local zip_file="$1"

	if [[ -z "${zip_file}" ]]; then
		echo "No ${PLUGIN_SLUG}-v*.zip archive found in ${ROOT_DIR}." >&2
		echo "Build a release first with ./scripts/build-release.sh, then re-run scripts/docker-setup.sh." >&2
		exit 1
	fi

	echo "Installing plugin from ${zip_file}..."
	${WP} plugin install "${CONTAINER_PLUGIN_REPO}/${zip_file}" --force --activate
}

sql_escape() {
	printf '%s' "$1" | sed "s/'/''/g"
}

reconcile_site_config() {
	${WP} option update home "${SITE_URL}" >/dev/null
	${WP} option update siteurl "${SITE_URL}" >/dev/null
	${WP} option update blogname "${WP_SITE_TITLE}" >/dev/null
	${WP} option update blogdescription "${WP_BLOG_DESCRIPTION}" >/dev/null
}

reconcile_admin_user() {
	local admin_id=""
	local admin_ids=""
	local sole_admin_id=""
	local sole_admin_login=""
	local user_table=""

	admin_id="$(${WP} user get "${WP_ADMIN_USERNAME}" --field=ID 2>/dev/null || true)"

	if [[ -z "${admin_id}" ]]; then
		admin_ids="$(${WP} user list --role=administrator --field=ID --format=ids 2>/dev/null || true)"

		if [[ -n "${admin_ids}" && "${admin_ids}" != *" "* ]]; then
			sole_admin_id="${admin_ids}"
			sole_admin_login="$(${WP} user get "${sole_admin_id}" --field=user_login 2>/dev/null || true)"

			if [[ -n "${sole_admin_login}" && "${sole_admin_login}" != "${WP_ADMIN_USERNAME}" ]]; then
				user_table="$(${WP} db prefix 2>/dev/null || printf 'wp_')users"
				${WP} db query "UPDATE ${user_table} SET user_login='$(sql_escape "${WP_ADMIN_USERNAME}")', user_nicename='$(sql_escape "${WP_ADMIN_USERNAME}")' WHERE ID=${sole_admin_id}" >/dev/null
				admin_id="${sole_admin_id}"
			fi
		fi
	fi

	if [[ -n "${admin_id}" ]]; then
		${WP} user update "${admin_id}" \
			--user_pass="${WP_ADMIN_PASSWORD}" \
			--user_email="${WP_ADMIN_EMAIL}" \
			--display_name="${WP_ADMIN_USERNAME}" \
			--nickname="${WP_ADMIN_USERNAME}" >/dev/null
		return
	fi

	${WP} user create "${WP_ADMIN_USERNAME}" "${WP_ADMIN_EMAIL}" \
		--role=administrator \
		--user_pass="${WP_ADMIN_PASSWORD}" >/dev/null
}

semver_gte() {
	local left="$1"
	local right="$2"
	local left_parts=()
	local right_parts=()
	local max_parts=0
	local i=0
	local left_value=0
	local right_value=0

	IFS=. read -r -a left_parts <<< "${left}"
	IFS=. read -r -a right_parts <<< "${right}"
	max_parts="${#left_parts[@]}"

	if (( ${#right_parts[@]} > max_parts )); then
		max_parts="${#right_parts[@]}"
	fi

	for (( i=0; i<max_parts; i++ )); do
		left_value="${left_parts[i]:-0}"
		right_value="${right_parts[i]:-0}"

		(( 10#${left_value} > 10#${right_value} )) && return 0
		(( 10#${left_value} < 10#${right_value} )) && return 1
	done

	return 0
}

wait_for_wordpress_db() {
	local attempts=0
	local max_attempts=40

	until ${WP} db check --quiet >/dev/null 2>&1; do
		((attempts += 1))
		if (( attempts >= max_attempts )); then
			echo "WordPress database did not become ready after $(( max_attempts * 3 )) seconds." >&2
			exit 1
		fi

		printf "."
		sleep 3
	done
}

env_load_file "${ENV_TEMPLATE}" "${ENV_KEYS[@]}"
env_load_file "${ENV_FILE}" "${ENV_KEYS[@]}"
bootstrap_defaults

prompt_var "SITE_PORT" "WordPress site port"
prompt_var "PMA_PORT" "phpMyAdmin port"
prompt_var "MARIADB_DATABASE" "MariaDB database name"
prompt_var "MARIADB_USER" "MariaDB application user"
prompt_var "MARIADB_PASSWORD" "MariaDB application password"
prompt_var "MARIADB_ROOT_PASSWORD" "MariaDB root password"
prompt_var "WORDPRESS_DB_HOST" "WordPress database host"
prompt_var "WORDPRESS_DB_NAME" "WordPress database name"
prompt_var "WORDPRESS_DB_USER" "WordPress database user"
prompt_var "WORDPRESS_DB_PASSWORD" "WordPress database password"
prompt_var "PMA_HOST" "phpMyAdmin database host"
prompt_var "PMA_USER" "phpMyAdmin username"
prompt_var "PMA_PASSWORD" "phpMyAdmin password"
prompt_var "WP_ADMIN_USERNAME" "WordPress admin username"
prompt_var "WP_ADMIN_PASSWORD" "WordPress admin password"
prompt_var "WP_ADMIN_EMAIL" "WordPress admin email"
prompt_var "WP_SITE_TITLE" "WordPress site title"
prompt_var "WP_BLOG_DESCRIPTION" "WordPress blog description"
prompt_var "OLLAMA_PROXY_PORT" "Ollama proxy port for the Docker stack"
prompt_var "OLLAMA_HOST_TARGET" "Local Ollama host target for the Docker stack"

write_env_file

WP="docker compose run --rm --no-deps wpcli wp"
SITE_URL="http://localhost:${SITE_PORT}"
PLUGIN_ZIP_FILE="$(resolve_latest_plugin_zip)"

echo "Starting containers with values from ${ENV_FILE}..."
docker compose up -d

echo "Waiting for WordPress to be ready..."
wait_for_wordpress_db
echo " ready."

if ${WP} core is-installed >/dev/null 2>&1; then
	echo "WordPress is already installed. Skipping core install."
else
	echo "Installing WordPress core..."
	${WP} core install \
		--url="${SITE_URL}" \
		--title="${WP_SITE_TITLE}" \
		--admin_user="${WP_ADMIN_USERNAME}" \
		--admin_password="${WP_ADMIN_PASSWORD}" \
		--admin_email="${WP_ADMIN_EMAIL}" \
		--skip-email
	echo "WordPress core installed."
fi

reconcile_site_config
reconcile_admin_user
install_plugin_zip "${PLUGIN_ZIP_FILE}"

${WP} rewrite structure '/%postname%/' --hard >/dev/null

POST_ID="$(
	${WP} post create \
		--post_title="AI Content Forge Test Post" \
		--post_status=draft \
		--post_type=post \
		--porcelain 2>/dev/null || true
)"

if [[ -n "${POST_ID}" ]]; then
	echo "Created draft post ${POST_ID} for editor testing."
fi

cat <<EOF

WordPress is ready.

Site: ${SITE_URL}
Admin: ${SITE_URL}/wp-admin
Login: ${WP_ADMIN_USERNAME} / ${WP_ADMIN_PASSWORD}
phpMyAdmin: http://localhost:${PMA_PORT}
Plugin settings: ${SITE_URL}/wp-admin/admin.php?page=ai-content-forge

The active Docker configuration is saved in:
  ${ENV_FILE}

For later runs:
  docker compose up -d
  docker compose down
  docker compose run --rm --no-deps wpcli wp plugin install ${CONTAINER_PLUGIN_REPO}/${PLUGIN_ZIP_FILE} --force --activate
EOF

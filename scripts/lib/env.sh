#!/usr/bin/env bash

env_trim_whitespace() {
    local value="$1"

    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"

    printf '%s' "${value}"
}

env_parse_value() {
    local value

    value="$(env_trim_whitespace "$1")"

    if [[ "${value}" =~ ^\"(.*)\"$ ]]; then
        value="${BASH_REMATCH[1]}"
        value="${value//\\\\/\\}"
        value="${value//\\\"/\"}"
        value="${value//\\\$/\$}"
        value="${value//\\\`/\`}"
    elif [[ "${value}" =~ ^\'(.*)\'$ ]]; then
        value="${BASH_REMATCH[1]}"
    fi

    printf '%s' "${value}"
}

env_escape_value() {
    printf '%s' "$1" | sed -e 's/[\\$`"]/\\&/g'
}

env_is_allowed_key() {
    local key="$1"
    shift || true

    if (( $# == 0 )); then
        return 0
    fi

    local allowed=""
    for allowed in "$@"; do
        if [[ "${allowed}" == "${key}" ]]; then
            return 0
        fi
    done

    return 1
}

env_load_file() {
    local file="$1"
    shift || true
    local line=""
    local key=""
    local value=""

    if [[ ! -f "${file}" ]]; then
        return
    fi

    while IFS= read -r line || [[ -n "${line}" ]]; do
        line="$(env_trim_whitespace "${line}")"

        if [[ -z "${line}" || "${line}" == \#* ]]; then
            continue
        fi

        if [[ ! "${line}" =~ ^([A-Z0-9_]+)=(.*)$ ]]; then
            continue
        fi

        key="${BASH_REMATCH[1]}"
        value="${BASH_REMATCH[2]}"

        if ! env_is_allowed_key "${key}" "$@"; then
            continue
        fi

        value="$(env_parse_value "${value}")"
        printf -v "${key}" '%s' "${value}"
        export "${key}"
    done < "${file}"
}

env_ensure_file() {
    local file="$1"
    local template="${2:-}"

    if [[ -f "${file}" ]]; then
        chmod 600 "${file}" 2>/dev/null || true
        return
    fi

    if [[ -n "${template}" && -f "${template}" ]]; then
        cp "${template}" "${file}"
    else
        : > "${file}"
    fi

    chmod 600 "${file}" 2>/dev/null || true
}

env_set() {
    local file="$1"
    local key="$2"
    local value="$3"
    local escaped_value=""
    local temp_file=""
    local found=0
    local line=""

    escaped_value="$(env_escape_value "${value}")"
    temp_file="$(mktemp)"

    if [[ -f "${file}" ]]; then
        while IFS= read -r line || [[ -n "${line}" ]]; do
            if [[ "${line}" =~ ^[[:space:]]*${key}= ]]; then
                if (( found == 0 )); then
                    printf '%s="%s"\n' "${key}" "${escaped_value}" >> "${temp_file}"
                    found=1
                fi
                continue
            fi

            printf '%s\n' "${line}" >> "${temp_file}"
        done < "${file}"
    fi

    if (( found == 0 )); then
        if [[ -s "${temp_file}" ]]; then
            printf '\n' >> "${temp_file}"
        fi
        printf '%s="%s"\n' "${key}" "${escaped_value}" >> "${temp_file}"
    fi

    mv "${temp_file}" "${file}"
    chmod 600 "${file}" 2>/dev/null || true
}

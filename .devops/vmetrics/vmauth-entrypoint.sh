#!/bin/sh
set -e

# Create config directory
mkdir -p /etc/vmauth

# Generate vmauth config from environment variables
cat > /etc/vmauth/config.yaml <<EOF
users:
  - username: ${VMAUTH_ADMIN_USER:-admin}
    password: ${VMAUTH_ADMIN_PASS:-SecureLogsPassword123!}
    url_map:
      - src_paths:
          - "/select/.*"
        url_prefix: "http://victoria-logs:9428"

  - username: ${VMAUTH_READONLY_USER:-readonly}
    password: ${VMAUTH_READONLY_PASS:-ReadOnlyLogsPass456@}
    url_map:
      - src_paths:
          - "/select/.*"
        url_prefix: "http://victoria-logs:9428"
EOF

# Start vmauth with generated config
exec /vmauth-prod -auth.config=/etc/vmauth/config.yaml

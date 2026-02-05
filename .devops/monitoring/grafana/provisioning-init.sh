#!/bin/sh

set -e

GRAFANA_URL="http://grafana:3000"
GRAFANA_USER="admin"
GRAFANA_PASSWORD="${GF_SECURITY_ADMIN_PASSWORD:-admin}"
# Use /dashboards if running in loader container, else /var/lib/grafana/dashboards
if [ -d "/dashboards" ]; then
  DASHBOARDS_DIR="/dashboards"
else
  DASHBOARDS_DIR="/var/lib/grafana/dashboards"
fi

echo "Waiting for Grafana to be ready..."
i=0
while [ $i -lt 60 ]; do
  if curl -s "$GRAFANA_URL/api/health" > /dev/null 2>&1; then
    echo "Grafana is ready!"
    break
  fi
  echo "Waiting... attempt $((i+1))"
  sleep 1
  i=$((i+1))
done

echo "Provisioning dashboards..."

for dashboard_file in "$DASHBOARDS_DIR"/*.json; do
  if [ -f "$dashboard_file" ]; then
    dashboard_name=$(basename "$dashboard_file" .json)
    echo "Importing dashboard: $dashboard_name"

    curl -s -X POST "$GRAFANA_URL/api/dashboards/db" \
      -H "Content-Type: application/json" \
      -u "$GRAFANA_USER:$GRAFANA_PASSWORD" \
      -d "{\"dashboard\": $(cat "$dashboard_file"), \"overwrite\": true}" | head -c 150

    echo ""
  fi
done

echo ""
echo "Dashboard provisioning complete!"

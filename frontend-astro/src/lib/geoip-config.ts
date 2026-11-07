export function isGeoIpLocaleRoutingEnabled(
  value: string | undefined = process.env.GEOIP_LOCALE_ROUTING_ENABLED,
): boolean {
  return value === "true";
}

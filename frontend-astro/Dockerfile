FROM node:24-alpine AS deps

WORKDIR /app

RUN corepack enable pnpm

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile

FROM deps AS build

ARG PUBLIC_SITE_URL=https://web3main.pro
ARG PUBLIC_SITE_TITLE=web3main.pro
ARG PUBLIC_SITE_AUTHOR="Web3main LLC"
ARG PUBLIC_SITE_DESC="Stay updated with the latest trends in Web3 development, software engineering tips, and best coding practices for modern applications."
ARG PUBLIC_GOOGLE_SITE_VERIFICATION=""

ENV PUBLIC_SITE_URL=${PUBLIC_SITE_URL} \
    PUBLIC_SITE_TITLE=${PUBLIC_SITE_TITLE} \
    PUBLIC_SITE_AUTHOR=${PUBLIC_SITE_AUTHOR} \
    PUBLIC_SITE_DESC=${PUBLIC_SITE_DESC} \
    PUBLIC_GOOGLE_SITE_VERIFICATION=${PUBLIC_GOOGLE_SITE_VERIFICATION} \
    ASTRO_TELEMETRY_DISABLED=1

COPY . .
RUN pnpm build

FROM node:24-alpine AS prod

# The runtime only needs node; drop npm/corepack so their bundled deps are not shipped
RUN rm -rf /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/corepack /usr/local/bin/npm /usr/local/bin/npx /usr/local/bin/corepack

WORKDIR /app

ENV NODE_ENV=production \
    HOST=0.0.0.0 \
    PORT=4321

COPY --from=build /app/dist ./dist
COPY --from=build /app/package.json ./package.json
COPY --from=build /app/node_modules ./node_modules

EXPOSE 4321

CMD ["node", "dist/server/entry.mjs"]

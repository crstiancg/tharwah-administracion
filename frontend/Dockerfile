# Frontend (Quasar SPA) para Dokploy: se compila con Node y lo sirve nginx.
#
# Las VITE_APP_* se graban en el JavaScript AL COMPILAR: en Dokploy van como
# "Build Args" (no como variables de entorno del contenedor).

FROM node:24-alpine AS build
WORKDIR /app

ARG VITE_APP_API_URL
ARG VITE_APP_CLIENT_ID
ARG VITE_APP_SECRET
ENV VITE_APP_API_URL=$VITE_APP_API_URL \
    VITE_APP_CLIENT_ID=$VITE_APP_CLIENT_ID \
    VITE_APP_SECRET=$VITE_APP_SECRET

COPY package.json package-lock.json ./
# Sin scripts: el postinstall (quasar prepare) necesita quasar.config.js, que
# todavía no se copió. Se corre después, ya con el código.
RUN npm ci --ignore-scripts

COPY . .
RUN test -n "$VITE_APP_API_URL" || (echo "Falta el Build Arg VITE_APP_API_URL" && exit 1) \
    && npx quasar prepare \
    && npx quasar build

FROM nginx:1.27-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/spa /usr/share/nginx/html
EXPOSE 80

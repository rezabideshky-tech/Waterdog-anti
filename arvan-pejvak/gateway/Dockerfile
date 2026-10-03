FROM node:22-alpine
WORKDIR /srv/pejvak
COPY package*.json ./
RUN npm ci --omit=dev
COPY src ./src
USER node
EXPOSE 8787
CMD ["npm", "start"]

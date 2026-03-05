# ==============================================================
# ReadInvoice Outlook Add-in
# Multi-stage build: compile with Node, serve with Nginx
# ==============================================================

# Stage 1: Build
FROM node:20-alpine AS builder

WORKDIR /app

# Copy package files first for better layer caching
COPY package.json package-lock.json* ./

# Install dependencies
RUN npm ci --ignore-scripts

# Copy source files
COPY tsconfig.json webpack.config.js ./
COPY src/ ./src/

# Build the webpack bundle
RUN npm run build

# Stage 2: Serve with Nginx
FROM nginx:1.25-alpine AS production

# Remove default nginx config
RUN rm -f /etc/nginx/conf.d/default.conf

# Add custom nginx configuration
COPY --from=builder /app/dist /usr/share/nginx/html

# Create nginx configuration
RUN cat > /etc/nginx/conf.d/readinvoice-addin.conf << 'NGINX_CONF'
server {
    listen 80;
    server_name _;

    root /usr/share/nginx/html;
    index taskpane.html;

    # Security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self' https://readinvoice.example.com; script-src 'self' https://appsforoffice.microsoft.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self' https://readinvoice.example.com;" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;

    # Remove server version header
    server_tokens off;

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # HTML files should not be cached aggressively
    location ~* \.html$ {
        expires -1;
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        add_header Pragma "no-cache";
    }

    # Health check endpoint
    location /health {
        access_log off;
        return 200 '{"status":"ok"}';
        add_header Content-Type application/json;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Handle SPA-style routing
    location / {
        try_files $uri $uri/ /taskpane.html;
    }
}
NGINX_CONF

# Run as non-root user
RUN chown -R nginx:nginx /usr/share/nginx/html && \
    chown -R nginx:nginx /var/cache/nginx && \
    chown -R nginx:nginx /var/log/nginx && \
    touch /var/run/nginx.pid && \
    chown nginx:nginx /var/run/nginx.pid

USER nginx

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]

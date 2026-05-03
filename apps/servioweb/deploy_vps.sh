#!/bin/bash

# ==================================================
# 🚀 Servio Custom Server VPS Deployment Script
# ==================================================

set -e

APP_NAME="Servio-web"

# --------------------------------------------------
# 🎨 Colors & Styles
# --------------------------------------------------
RESET="\033[0m"
BOLD="\033[1m"

RED="\033[31m"
GREEN="\033[32m"
YELLOW="\033[33m"
BLUE="\033[34m"
CYAN="\033[36m"

CHECK="✅"
CROSS="❌"
INFO="ℹ️"
ROCKET="🚀"
BROOM="🧹"
BOX="📦"
GEAR="⚙️"

# --------------------------------------------------
# 🧰 Helpers
# --------------------------------------------------
log() {
  echo -e "${BLUE}${INFO}${RESET} $1"
}

success() {
  echo -e "${GREEN}${CHECK}${RESET} $1"
}

warn() {
  echo -e "${YELLOW}⚠️${RESET} $1"
}

error() {
  echo -e "${RED}${CROSS}${RESET} $1"
  exit 1
}

STEP=1
TOTAL=8
step() {
  echo ""
  echo -e "${BOLD}${CYAN}[$STEP/$TOTAL] $1${RESET}"
  STEP=$((STEP+1))
}

spinner() {
  local pid=$1
  local msg=$2
  local spin='⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏'
  local i=0

  while ps -p $pid >/dev/null 2>&1; do
    printf "\r${CYAN}%s${RESET} %s" "${spin:i++%${#spin}:1}" "$msg"
    sleep 0.1
  done
  printf "\r${GREEN}${CHECK}${RESET} %s\n" "$msg"
}

# --------------------------------------------------
# Cross-platform sed
# --------------------------------------------------
replace_in_file() {
  local pattern=$1
  local file=$2
  if [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' "$pattern" "$file"
  else
    sed -i "$pattern" "$file"
  fi
}

clear
echo "--------------------------------------------------"
echo -e "${BOLD}${ROCKET} Servio VPS Deployment (Custom Server)${RESET}"
echo "--------------------------------------------------"

# --------------------------------------------------
# 1. Verify Custom Server
# --------------------------------------------------
step "Verifying custom server setup"
if [ ! -f "server.js" ]; then
  error "server.js not found! Custom server is required."
fi
success "Custom server found"

# --------------------------------------------------
# 2. Port & PM2 Configuration
# --------------------------------------------------
step "PM2 & Port Configuration"

# Try to get port from ecosystem.config.cjs, fallback to 8001
CURRENT_PORT=$(grep -o "NODE_PORT: [0-9]*" ecosystem.config.cjs 2>/dev/null | grep -o "[0-9]*" | head -1)
[ -z "$CURRENT_PORT" ] && CURRENT_PORT=8001

echo -e "Current Port: ${GREEN}$CURRENT_PORT${RESET}"
read -p "➜ Enter Port (Press Enter to keep $CURRENT_PORT): " INPUT_PORT
PORT=${INPUT_PORT:-$CURRENT_PORT}

log "Checking PM2 processes..."
PM2_EXISTS=$(pm2 list | grep -w "$APP_NAME" || true)
PORT_IN_USE=$(lsof -i :"$PORT" -sTCP:LISTEN -t 2>/dev/null || true)

RESTART_ONLY=false
if [[ -n "$PORT_IN_USE" && -z "$PM2_EXISTS" ]]; then
  error "Port $PORT already in use by another process"
fi

if [[ -n "$PM2_EXISTS" ]]; then
  EXISTING_PORT=$(pm2 env "$APP_NAME" 2>/dev/null | grep NODE_PORT= | cut -d= -f2 || echo "")
  if [[ "$EXISTING_PORT" == "$PORT" ]]; then
    RESTART_ONLY=true
    success "PM2 app exists on same port — will restart only"
  else
    warn "PM2 app exists with different port — will recreate"
    pm2 delete "$APP_NAME"
  fi
fi

# --------------------------------------------------
# 3. Clean Build Artifacts
# --------------------------------------------------
step "Cleaning old builds"
rm -rf .next out dist
mkdir -p logs .well-known
success "Clean complete"

# --------------------------------------------------
# 4. Install Dependencies
# --------------------------------------------------
step "Installing dependencies"
npm install >/dev/null 2>&1 &
spinner $! "Dependencies installed"

# --------------------------------------------------
# 5. Generate Sitemap & SW
# --------------------------------------------------
step "Generating sitemap & service worker"
if [ -f "scripts/setup-sitemap.js" ]; then
  node scripts/setup-sitemap.js
fi
if [ -f "scripts/generate-sw.js" ]; then
  node scripts/generate-sw.js
fi
success "Assets generated"

# --------------------------------------------------
# 6. Build Next.js (Custom Server Mode)
# --------------------------------------------------
step "Building Next.js (Custom Server)"

# Enable SEO for custom server mode
export NEXT_PUBLIC_ENABLE_SEO="true"
export NODE_ENV="production"

# Update ecosystem.config.cjs with new port
replace_in_file "s/NODE_PORT: [0-9]*/NODE_PORT: $PORT/g" ecosystem.config.cjs

npm run build >/dev/null 2>&1 &
spinner $! "Build complete"

[ ! -d ".next" ] && error "Build failed - .next directory not found"

# --------------------------------------------------
# 7. Generate Apache .htaccess
# --------------------------------------------------
step "Generating Apache configuration"

cat > .htaccess <<EOF
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Allow favicon
    RewriteRule ^favicon\.ico\$ - [L]
    
    # Allow SSL certificate verification (Let's Encrypt)
    RewriteRule ^\.well-known/acme-challenge/(.*) \$0 [L]
    
    # Serve Next.js static files directly from filesystem (performance optimization)
    # These files have cache-busting hashes, safe to serve directly
    RewriteRule ^_next/static/(.*) /.next/static/\$1 [L]
    
    # Proxy Next.js data requests to custom server (required for SSR/ISR)
    # These contain page props and must go through the Node.js server
    RewriteRule ^_next/data/(.*) http://127.0.0.1:$PORT/_next/data/\$1 [P,L]
    
    # Serve public folder static assets directly (images, fonts, etc.)
    RewriteCond %{REQUEST_URI} \.(js|css|svg|jpg|jpeg|png|gif|ico|woff|woff2|ttf|eot|webp|mp4|webm|json|xml)\$
    RewriteRule ^ - [L]
    
    # Proxy all other requests to custom Node.js server
    # This handles SSR, API routes, and dynamic pages
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)\$ http://127.0.0.1:$PORT/\$1 [P,L]
    
    # Fallback for index files
    RewriteRule ^index\.(html|php)\$ http://127.0.0.1:$PORT/ [P,L]
</IfModule>

# Security headers (optional but recommended)
<IfModule mod_headers.c>
    # Prevent clickjacking
    Header always set X-Frame-Options "SAMEORIGIN"
    
    # Prevent MIME type sniffing
    Header always set X-Content-Type-Options "nosniff"
    
    # Enable XSS protection
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>
EOF

success ".htaccess generated for port $PORT"

# --------------------------------------------------
# 8. Manage PM2 Process
# --------------------------------------------------
step "Managing PM2 process"

if [[ "$RESTART_ONLY" == "true" ]]; then
  log "Restarting existing PM2 process..."
  pm2 restart "$APP_NAME"
else
  log "Starting new PM2 process..."
  pm2 start ecosystem.config.cjs
fi

pm2 save
success "PM2 configured and saved"

# --------------------------------------------------
# 9. Reload Apache
# --------------------------------------------------
step "Reloading Apache"

if command -v systemctl >/dev/null; then
  sudo systemctl reload apache2 2>/dev/null || sudo systemctl reload httpd 2>/dev/null || warn "Apache reload failed"
  success "Apache reloaded via systemctl"
elif command -v service >/dev/null; then
  sudo service apache2 reload 2>/dev/null || sudo service httpd reload 2>/dev/null || warn "Apache reload failed"
  success "Apache reloaded via service"
else
  warn "Apache reload skipped (command not found)"
fi

# --------------------------------------------------
# DONE
# --------------------------------------------------
echo ""
echo "=================================================="
echo -e "${GREEN}${ROCKET} DEPLOYMENT SUCCESSFUL${RESET}"
echo "=================================================="
echo -e "App Name : ${BOLD}$APP_NAME${RESET}"
echo -e "Mode     : ${BOLD}Custom Server (server.js)${RESET}"
echo -e "Port     : ${CYAN}$PORT${RESET}"
echo -e "URL      : ${CYAN}http://localhost:$PORT${RESET}"
echo "=================================================="
echo -e "${YELLOW}💡 Benefits:${RESET}"
echo -e "  • Lower memory usage (~100-150MB vs 200-300MB)"
echo -e "  • Custom request handling & rate limiting"
echo -e "  • .well-known directory support"
echo -e "  • Enhanced security features"
echo "=================================================="
echo ""

pm2 ls

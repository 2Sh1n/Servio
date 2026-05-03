/**
 * PM2 Ecosystem Configuration for Custom Server Mode
 * Optimized for KVM 4 / Low-Resource VPS Servers
 * 
 * Uses custom server.js instead of standalone mode for better memory efficiency.
 * 
 * Usage:
 *   pm2 start ecosystem.config.cjs
 *   pm2 restart Servio-web
 *   pm2 stop Servio-web
 *   pm2 delete Servio-web
 *   pm2 monit  # Monitor resource usage
 */
module.exports = {
    apps: [
        {
            name: "Servio-web",
            script: "server.js",  // Custom server instead of .next/standalone/server.js
            cwd: "./",

            // Use only 1 instance for low-resource servers
            instances: 1,
            exec_mode: "fork",

            env: {
                NODE_ENV: "production",
                NODE_PORT: 8001,  // Custom server uses NODE_PORT
                HOSTNAME: "0.0.0.0",

                // Node.js memory limits - more conservative for custom server
                NODE_OPTIONS: "--max-old-space-size=256"
            },

            // Restart on file changes (disabled for production)
            watch: false,

            // Memory restart threshold - custom server uses less memory
            max_memory_restart: "200M",

            // Logging configuration
            error_file: "./logs/pm2-error.log",
            out_file: "./logs/pm2-out.log",
            log_file: "./logs/pm2-combined.log",
            time: true,
            log_date_format: "YYYY-MM-DD HH:mm:ss Z",
            merge_logs: true,

            // Restart behavior
            autorestart: true,
            max_restarts: 5,
            min_uptime: "10s",
            restart_delay: 3000,

            // Kill timeout
            kill_timeout: 5000,
            listen_timeout: 10000,
            exp_backoff_restart_delay: 100,

            // Graceful shutdown
            wait_ready: false,
            shutdown_with_message: false
        }
    ]
};

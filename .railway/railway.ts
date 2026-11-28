import {
    defineRailway,
    github,
    group,
    postgres,
    project,
    redis,
    service,
} from "railway/iac";

const source = github("returnearly/nominal", { branch: "master" });

const imageBuild = {
    builder: "DOCKERFILE" as const,
    dockerfilePath: "docker/Dockerfile",
};

const appKey = {
    generator:
        'base64:${{secret(43, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/")}}=',
};

const reverbKey = {
    generator: '${{secret(32, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789")}}',
};

const reverbSecret = {
    generator: '${{secret(48, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789")}}',
};

export default defineRailway(() => {
    const db = postgres("Postgres");
    const cache = redis("Redis");

    const runtime = {
        APP_NAME: "Nominal",
        APP_ENV: "production",
        APP_DEBUG: "false",
        APP_URL: { value: "https://${{web.RAILWAY_PUBLIC_DOMAIN}}" },
        LOG_CHANNEL: "stderr",
        LOG_LEVEL: "info",
        DB_CONNECTION: "pgsql",
        DB_URL: db.env.DATABASE_URL,
        DB_SSLMODE: "disable",
        CACHE_STORE: "redis",
        REDIS_CLIENT: "phpredis",
        REDIS_URL: cache.env.REDIS_URL,
        SESSION_DRIVER: "database",
        QUEUE_CONNECTION: "database",
        BROADCAST_CONNECTION: "reverb",
        PROBE_REGION: "local",
        OCTANE_SERVER: "frankenphp",
        AUTORUN_ENABLED: "false",
        SSL_MODE: "off",
        INTERFACE_AUTH: "none",
        MAIL_MAILER: "log",
        MAIL_FROM_ADDRESS: "alerts@nominal.local",
        REVERB_APP_ID: "nominal",
        REVERB_HOST: { value: "${{reverb.RAILWAY_PRIVATE_DOMAIN}}" },
        REVERB_PORT: "8080",
        REVERB_SCHEME: "http",
        REVERB_SERVER_HOST: "0.0.0.0",
        REVERB_SERVER_PORT: "8080",
    };

    const web = service("web", {
        source,
        build: imageBuild,
        start: "php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8080 --admin-port=2019",
        preDeploy: "sh /var/www/html/deploy/railway/migrate.sh",
        healthcheck: "/up",
        healthcheckTimeout: 300,
        deploy: { preDeployTimeoutSeconds: 300 },
        networking: {
            serviceDomains: {
                "<hasDomain>:8080": { port: 8080 },
            },
        },
        env: {
            ...runtime,
            NOMINAL_PROCESS: "web",
            PORT: "8080",
            APP_KEY: appKey,
            REVERB_APP_KEY: reverbKey,
            REVERB_APP_SECRET: reverbSecret,
            REVERB_CLIENT_HOST: { value: "${{reverb.RAILWAY_PUBLIC_DOMAIN}}" },
            REVERB_CLIENT_PORT: "443",
            REVERB_CLIENT_SCHEME: "https",
        },
    });

    const secrets = {
        APP_KEY: web.env.APP_KEY,
        REVERB_APP_KEY: web.env.REVERB_APP_KEY,
        REVERB_APP_SECRET: web.env.REVERB_APP_SECRET,
    };

    const worker = service("worker", {
        source,
        build: imageBuild,
        start: "sh /var/www/html/deploy/railway/worker.sh",
        env: {
            ...runtime,
            ...secrets,
            NOMINAL_PROCESS: "worker",
        },
    });

    const scheduler = service("scheduler", {
        source,
        build: imageBuild,
        start: "sh /var/www/html/deploy/railway/scheduler.sh",
        env: {
            ...runtime,
            ...secrets,
            NOMINAL_PROCESS: "scheduler",
        },
    });

    const reverb = service("reverb", {
        source,
        build: imageBuild,
        start: "php artisan reverb:start --host=0.0.0.0 --port=8080",
        networking: {
            serviceDomains: {
                "<hasDomain>:8080": { port: 8080 },
            },
        },
        env: {
            ...runtime,
            ...secrets,
            NOMINAL_PROCESS: "reverb",
            PORT: "8080",
        },
    });

    return project("nominal", {
        resources: [
            group("Nominal", [web, worker, scheduler, reverb]),
            group("Data", [db, cache]),
        ],
    });
});

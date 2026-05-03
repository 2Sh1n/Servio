import { useEffect } from "react";
import { gt } from "semver";

const VersionUpdater = () => {

    useEffect(() => {
        const handleUpdate = async (version) => {
            try {
                // 1. Clear LocalStorage and SessionStorage
                localStorage.clear();
                sessionStorage.clear();

                // 2. Clear Cookies
                const cookies = document.cookie.split(";");
                for (let i = 0; i < cookies.length; i++) {
                    const cookie = cookies[i];
                    const eqPos = cookie.indexOf("=");
                    const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
                    document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
                }

                // 3. Clear Service Workers
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    for (let registration of registrations) {
                        await registration.unregister();
                    }
                }

                // 4. Clear Caches
                if ("caches" in window) {
                    const names = await caches.keys();
                    await Promise.all(names.map(name => caches.delete(name)));
                }

            } catch (error) {
                console.error("Error during version update cleanup:", error);
            } finally {
                // 5. Set new version to prevent loop
                if (version) {
                    localStorage.setItem("app_version", version);
                } else {
                    localStorage.setItem("app_version", process.env.NEXT_PUBLIC_WEB_VERSION);
                }

                // 6. Reload
                window.location.reload(true);
            }
        };

        const checkVersion = () => {
            const currentVersion = process.env.NEXT_PUBLIC_WEB_VERSION;
            const storedVersion = localStorage.getItem("app_version");

            if (!currentVersion) return; // Exit if environment variable is missing

            if (storedVersion) {
                if (gt(currentVersion, storedVersion)) {
                    handleUpdate(currentVersion);
                }
            } else {
                // If no version is stored, store the current one (first visit or fresh storage)
                localStorage.setItem("app_version", currentVersion);
            }
        };

        checkVersion();
    }, []);

    return null;
};

export default VersionUpdater;

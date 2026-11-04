import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.BmsTrash = {
    key: 'bms.trash',
    read() {
        const raw = localStorage.getItem(this.key);
        if (!raw) return [];

        const items = JSON.parse(raw);
        return Array.isArray(items) ? items : [];
    },
    readCollection(key, fallback) {
        const raw = localStorage.getItem(key);
        if (!raw) return fallback;

        const items = JSON.parse(raw);
        return Array.isArray(items) ? items : fallback;
    },
    writeCollection(key, items) {
        localStorage.setItem(key, JSON.stringify(items));
    },
    add(type, label, item, sourceKey, restoreContext = null) {
        const items = this.read();
        items.unshift({
            id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            type,
            label,
            item,
            sourceKey,
            restoreContext,
            deletedAt: new Date().toISOString(),
        });
        localStorage.setItem(this.key, JSON.stringify(items));
        return items;
    },
    restore(id) {
        const items = this.read();
        const entry = items.find((item) => item.id === id);
        if (!entry) return false;

        if (entry.sourceKey) {
            const collection = this.readCollection(entry.sourceKey, []);
            if (entry.restoreContext?.path === 'stay.charges') {
                const room = collection.find((item) => item.id === entry.restoreContext.roomId);
                if (room?.stay) {
                    room.stay.charges = room.stay.charges || [];
                    if (!room.stay.charges.some((charge) => charge.id === entry.item.id)) {
                        room.stay.charges.push(entry.item);
                    }
                }
            } else {
                const exists = collection.some((item) => {
                    if (item && entry.item && typeof item === 'object' && typeof entry.item === 'object') {
                        if (Object.hasOwn(item, 'id') && Object.hasOwn(entry.item, 'id')) {
                            return item.id === entry.item.id;
                        }
                        return JSON.stringify(item) === JSON.stringify(entry.item);
                    }
                    return item === entry.item;
                });
                if (!exists) collection.push(entry.item);
            }
            this.writeCollection(entry.sourceKey, collection);
        }

        localStorage.setItem(this.key, JSON.stringify(items.filter((item) => item.id !== id)));
        return entry;
    },
    permanentlyDelete(id) {
        const items = this.read();
        const remaining = items.filter((item) => item.id !== id);
        if (remaining.length === items.length) return false;
        localStorage.setItem(this.key, JSON.stringify(remaining));
        return true;
    },
};

window.BmsRooms = {
    key: 'bms.roomsEnabled',
    enabled() {
        return localStorage.getItem(this.key) !== 'false';
    },
    setEnabled(enabled) {
        localStorage.setItem(this.key, enabled ? 'true' : 'false');
        window.dispatchEvent(new CustomEvent('bms-rooms-module-updated', {
            detail: { enabled },
        }));
    },
};

document.addEventListener('DOMContentLoaded', () => {
    Alpine.start();
});

(() => {
    let locked = false;
    let timer = null;

    const lock = () => {
        if (locked) return;
        locked = true;
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
    };

    const unlock = () => {
        if (!locked) return;
        locked = false;
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
    };

    const isVisible = (el) => el.getClientRects().length > 0;

    const scan = () => {
        const overlays = document.querySelectorAll('.fixed.inset-0');
        let open = false;
        for (let i = 0; i < overlays.length; i++) {
            if (isVisible(overlays[i])) { open = true; break; }
        }
        open ? lock() : unlock();
    };

    const observer = new MutationObserver(() => {
        clearTimeout(timer);
        timer = setTimeout(scan, 30);
    });

    document.addEventListener('DOMContentLoaded', () => {
        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['style', 'class'],
            subtree: true,
            childList: true,
        });
    });
})();

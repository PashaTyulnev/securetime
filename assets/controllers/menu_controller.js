// javascript
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sidebar', 'lightModeTrigger', 'darkModeTrigger'];

    connect() {
        // Backdrop: найти или создать
        this.backdrop = document.querySelector('[data-drawer-backdrop]');
        if (!this.backdrop) {
            this.backdrop = document.createElement('div');
            this.backdrop.setAttribute('data-drawer-backdrop', '');
            this.backdrop.className = 'fixed inset-0 z-30 bg-black/50 hidden';
            document.body.appendChild(this.backdrop);
        }

        // Обработчики
        this._onBackdropClick = this._onBackdropClick.bind(this);
        this.backdrop.addEventListener('click', this._onBackdropClick);
        this._onResize = this._onResize.bind(this);
        window.addEventListener('resize', this._onResize);

        // Theme init
        const theme = localStorage.getItem('theme');
        const isDarkMode = theme === 'dark';
        if (isDarkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        this.setTriggerVisibility(isDarkMode);

        // Синхронизировать состояние сайдбара по размеру/при навигации
        this._closeIfNeeded();
    }

    disconnect() {
        if (this.backdrop) {
            this.backdrop.removeEventListener('click', this._onBackdropClick);
        }
        window.removeEventListener('resize', this._onResize);
    }

    // Переключение видимости иконок тем
    setTriggerVisibility(isDarkMode) {
        if (!this.hasLightModeTriggerTarget || !this.hasDarkModeTriggerTarget) return;

        if (isDarkMode) {
            this.lightModeTriggerTarget.classList.remove('hidden');
            this.darkModeTriggerTarget.classList.add('hidden');
        } else {
            this.lightModeTriggerTarget.classList.add('hidden');
            this.darkModeTriggerTarget.classList.remove('hidden');
        }
    }

    // Toggle sidebar, привязывать к data-action="click->menu#toggleSidebar"
    toggleSidebar(event) {
        if (event && event.preventDefault) event.preventDefault();
        if (!this.hasSidebarTarget) return;

        const el = this.sidebarTarget;
        const isHidden = el.classList.contains('-translate-x-full');

        if (isHidden) {
            el.classList.remove('-translate-x-full');
            this.backdrop.classList.remove('hidden');
        } else {
            el.classList.add('-translate-x-full');
            this.backdrop.classList.add('hidden');
        }
    }

    // Toggle dark mode, привязывать к соответствующим кнопкам
    toggleDarkMode(event) {
        if (event && event.preventDefault) event.preventDefault();

        const html = document.documentElement;
        const currentTheme = localStorage.getItem('theme');
        let newTheme;

        if (currentTheme !== 'dark') {
            html.classList.add('dark');
            newTheme = 'dark';
        } else {
            html.classList.remove('dark');
            newTheme = 'light';
        }

        localStorage.setItem('theme', newTheme);
        this.setTriggerVisibility(newTheme === 'dark');
    }

    // Закрыть сайдбар на мобильных или при ресайзе
    _closeIfNeeded() {
        if (!this.hasSidebarTarget) return;
        const el = this.sidebarTarget;
        if (window.innerWidth < 640) {
            el.classList.add('-translate-x-full');
            this.backdrop.classList.add('hidden');
        } else {
            // на больших экранах можно гарантировать видимость (если требуется)
            el.classList.remove('-translate-x-full');
            this.backdrop.classList.add('hidden');
        }
    }

    _onBackdropClick() {
        if (!this.hasSidebarTarget) return;
        this.sidebarTarget.classList.add('-translate-x-full');
        this.backdrop.classList.add('hidden');
    }

    _onResize() {
        this._closeIfNeeded();
    }
}

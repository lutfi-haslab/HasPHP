/**
 * HasJS - A lightweight JavaScript framework for HasPHP
 * Features: State management, hooks, routing, lifecycle, global context
 * Optimized for small footprint and performance
 */
class HasJS {
    constructor() {
        this.components = new Map();
        this.globalState = new Proxy({}, {
            set: (target, prop, value) => {
                target[prop] = value;
                this.triggerGlobalUpdate(prop, value);
                return true;
            }
        });
        this.contexts = new Map();
        this.router = new HasRouter();
        this.api = new HasAPI();
        this.mounted = false;
        
        // Auto-mount when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.mount());
        } else {
            this.mount();
        }
    }

    // Component registration and management
    component(name, definition) {
        this.components.set(name, new HasComponent(name, definition, this));
        return this;
    }

    // Global state management
    setState(key, value) {
        this.globalState[key] = value;
        return this;
    }

    getState(key) {
        return this.globalState[key];
    }

    // Context management
    createContext(name, defaultValue = {}) {
        this.contexts.set(name, { value: defaultValue, subscribers: new Set() });
        return this;
    }

    getContext(name) {
        return this.contexts.get(name)?.value;
    }

    updateContext(name, value) {
        const context = this.contexts.get(name);
        if (context) {
            context.value = { ...context.value, ...value };
            context.subscribers.forEach(callback => callback(context.value));
        }
        return this;
    }

    subscribeToContext(name, callback) {
        const context = this.contexts.get(name);
        if (context) {
            context.subscribers.add(callback);
            // Return unsubscribe function
            return () => context.subscribers.delete(callback);
        }
        return () => {};
    }

    // Lifecycle management
    mount() {
        if (this.mounted) return;
        this.mounted = true;
        
        // Initialize all components
        this.components.forEach(component => component.mount());
        
        // Initialize router
        this.router.init();
        
        // Trigger global mounted lifecycle
        this.triggerLifecycle('mounted');
        
        return this;
    }

    unmount() {
        this.components.forEach(component => component.unmount());
        this.router.destroy();
        this.mounted = false;
        this.triggerLifecycle('unmounted');
        return this;
    }

    // Internal methods
    triggerGlobalUpdate(key, value) {
        this.components.forEach(component => {
            if (component.watchedKeys.has(key)) {
                component.update();
            }
        });
    }

    triggerLifecycle(event, ...args) {
        this.components.forEach(component => {
            if (component.lifecycle[event]) {
                component.lifecycle[event](...args);
            }
        });
    }
}

// Component class with hooks and lifecycle
class HasComponent {
    constructor(name, definition, app) {
        this.name = name;
        this.app = app;
        this.element = null;
        this.state = {};
        this.hooks = {
            useState: this.useState.bind(this),
            useEffect: this.useEffect.bind(this),
            useContext: this.useContext.bind(this),
            useRouter: this.useRouter.bind(this),
            useAPI: this.useAPI.bind(this)
        };
        this.watchedKeys = new Set();
        this.effects = [];
        this.contextSubscriptions = [];
        this.lifecycle = {};
        
        // Apply definition
        if (typeof definition === 'function') {
            definition.call(this, this.hooks);
        } else {
            Object.assign(this, definition);
        }
    }

    // Hook: useState
    useState(initialValue) {
        const key = `_${Object.keys(this.state).length}`;
        this.state[key] = initialValue;
        
        const setState = (newValue) => {
            const oldValue = this.state[key];
            this.state[key] = typeof newValue === 'function' ? newValue(oldValue) : newValue;
            this.update();
        };
        
        return [() => this.state[key], setState];
    }

    // Hook: useEffect
    useEffect(callback, dependencies = []) {
        this.effects.push({ callback, dependencies, cleanup: null });
        return this;
    }

    // Hook: useContext
    useContext(name) {
        const unsubscribe = this.app.subscribeToContext(name, () => this.update());
        this.contextSubscriptions.push(unsubscribe);
        return () => this.app.getContext(name);
    }

    // Hook: useRouter
    useRouter() {
        return {
            navigate: this.app.router.navigate.bind(this.app.router),
            currentRoute: () => this.app.router.currentRoute,
            params: () => this.app.router.params,
            query: () => this.app.router.query,
            push: this.app.router.push.bind(this.app.router),
            replace: this.app.router.replace.bind(this.app.router),
            back: this.app.router.back.bind(this.app.router)
        };
    }

    // Hook: useAPI
    useAPI() {
        return {
            get: this.app.api.get.bind(this.app.api),
            post: this.app.api.post.bind(this.app.api),
            put: this.app.api.put.bind(this.app.api),
            delete: this.app.api.delete.bind(this.app.api),
            setBaseURL: this.app.api.setBaseURL.bind(this.app.api),
            setHeaders: this.app.api.setHeaders.bind(this.app.api)
        };
    }

    // Watch global state keys
    watch(keys) {
        if (Array.isArray(keys)) {
            keys.forEach(key => this.watchedKeys.add(key));
        } else {
            this.watchedKeys.add(keys);
        }
        return this;
    }

    // Update component
    update() {
        if (this.render && this.element) {
            this.runEffects();
            this.lifecycle.updated?.();
        }
        return this;
    }

    // Mount component
    mount() {
        this.lifecycle.beforeMount?.();
        
        // Find element if selector provided
        if (this.selector) {
            this.element = document.querySelector(this.selector);
        }
        
        this.runEffects();
        this.lifecycle.mounted?.();
        return this;
    }

    // Unmount component
    unmount() {
        this.lifecycle.beforeUnmount?.();
        
        // Cleanup effects
        this.effects.forEach(effect => {
            if (effect.cleanup) effect.cleanup();
        });
        
        // Cleanup context subscriptions
        this.contextSubscriptions.forEach(unsubscribe => unsubscribe());
        
        this.lifecycle.unmounted?.();
        return this;
    }

    // Run effects
    runEffects() {
        this.effects.forEach(effect => {
            if (effect.cleanup) effect.cleanup();
            effect.cleanup = effect.callback() || null;
        });
    }
}

// Router class with browser history support
class HasRouter {
    constructor() {
        this.routes = new Map();
        this.currentRoute = '';
        this.params = {};
        this.query = {};
        this.beforeRouteChange = null;
        this.afterRouteChange = null;
        this.basePath = '';
    }

    // Define routes
    route(path, component, options = {}) {
        this.routes.set(path, { component, options });
        return this;
    }

    // Set base path for nested routing
    setBasePath(path) {
        this.basePath = path.replace(/\/$/, '');
        return this;
    }

    // Navigation methods
    navigate(path, updateHistory = true) {
        const fullPath = this.basePath + path;
        
        if (this.beforeRouteChange) {
            const shouldContinue = this.beforeRouteChange(this.currentRoute, path);
            if (shouldContinue === false) return this;
        }

        this.currentRoute = path;
        this.parseRoute(path);
        
        if (updateHistory) {
            window.history.pushState({ path }, '', fullPath);
        }
        
        this.afterRouteChange?.(path);
        return this;
    }

    push(path) {
        return this.navigate(path, true);
    }

    replace(path) {
        const fullPath = this.basePath + path;
        this.currentRoute = path;
        this.parseRoute(path);
        window.history.replaceState({ path }, '', fullPath);
        this.afterRouteChange?.(path);
        return this;
    }

    back() {
        window.history.back();
        return this;
    }

    // Parse current route for params and query
    parseRoute(path) {
        const [routePath, queryString] = path.split('?');
        
        // Parse query parameters
        this.query = {};
        if (queryString) {
            queryString.split('&').forEach(param => {
                const [key, value] = param.split('=');
                this.query[decodeURIComponent(key)] = decodeURIComponent(value || '');
            });
        }

        // Parse route parameters
        this.params = {};
        for (const [pattern, routeData] of this.routes) {
            const regex = pattern.replace(/:\w+/g, '([^/]+)');
            const match = routePath.match(new RegExp(`^${regex}$`));
            
            if (match) {
                const paramNames = pattern.match(/:\w+/g) || [];
                paramNames.forEach((paramName, index) => {
                    this.params[paramName.slice(1)] = match[index + 1];
                });
                break;
            }
        }
    }

    // Route guards
    beforeEach(callback) {
        this.beforeRouteChange = callback;
        return this;
    }

    afterEach(callback) {
        this.afterRouteChange = callback;
        return this;
    }

    // Initialize router
    init() {
        // Handle browser back/forward
        window.addEventListener('popstate', (event) => {
            const path = event.state?.path || this.getCurrentPath();
            this.navigate(path, false);
        });

        // Handle initial route
        const initialPath = this.getCurrentPath();
        this.navigate(initialPath, false);
        
        return this;
    }

    getCurrentPath() {
        let path = window.location.pathname;
        if (this.basePath && path.startsWith(this.basePath)) {
            path = path.slice(this.basePath.length);
        }
        return path || '/';
    }

    destroy() {
        window.removeEventListener('popstate', this.handlePopState);
        return this;
    }
}

// API client with caching and error handling
class HasAPI {
    constructor() {
        this.baseURL = '';
        this.defaultHeaders = {
            'Content-Type': 'application/json'
        };
        this.cache = new Map();
        this.interceptors = {
            request: [],
            response: []
        };
    }

    setBaseURL(url) {
        this.baseURL = url.replace(/\/$/, '');
        return this;
    }

    setHeaders(headers) {
        this.defaultHeaders = { ...this.defaultHeaders, ...headers };
        return this;
    }

    // Interceptors
    interceptRequest(callback) {
        this.interceptors.request.push(callback);
        return this;
    }

    interceptResponse(callback) {
        this.interceptors.response.push(callback);
        return this;
    }

    // HTTP methods
    async get(url, options = {}) {
        return this.request('GET', url, null, options);
    }

    async post(url, data, options = {}) {
        return this.request('POST', url, data, options);
    }

    async put(url, data, options = {}) {
        return this.request('PUT', url, data, options);
    }

    async delete(url, options = {}) {
        return this.request('DELETE', url, null, options);
    }

    // Main request method
    async request(method, url, data = null, options = {}) {
        const fullURL = this.baseURL + url;
        const cacheKey = `${method}:${fullURL}`;
        
        // Check cache for GET requests
        if (method === 'GET' && !options.noCache && this.cache.has(cacheKey)) {
            const cached = this.cache.get(cacheKey);
            if (Date.now() - cached.timestamp < (options.cacheTime || 300000)) { // 5min default
                return cached.data;
            }
        }

        let config = {
            method,
            headers: { ...this.defaultHeaders, ...options.headers },
            ...options
        };

        if (data && method !== 'GET') {
            config.body = typeof data === 'string' ? data : JSON.stringify(data);
        }

        // Apply request interceptors
        for (const interceptor of this.interceptors.request) {
            config = await interceptor(config) || config;
        }

        try {
            const response = await fetch(fullURL, config);
            let result = response;

            // Apply response interceptors
            for (const interceptor of this.interceptors.response) {
                result = await interceptor(result) || result;
            }

            // Parse JSON if response is ok
            if (result.ok) {
                const data = await result.json();
                
                // Cache GET requests
                if (method === 'GET' && !options.noCache) {
                    this.cache.set(cacheKey, { data, timestamp: Date.now() });
                }
                
                return data;
            } else {
                throw new Error(`HTTP ${result.status}: ${result.statusText}`);
            }
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }

    // Clear cache
    clearCache(pattern = null) {
        if (pattern) {
            for (const key of this.cache.keys()) {
                if (key.includes(pattern)) {
                    this.cache.delete(key);
                }
            }
        } else {
            this.cache.clear();
        }
        return this;
    }
}

// Initialize and export HasJS instance
window.HasJS = new HasJS();

// Ensure HasJS is fully ready
window.HasJS.ready = function(callback) {
    if (this.mounted) {
        callback(this);
    } else {
        // Wait for mounting to complete
        const checkReady = () => {
            if (this.mounted) {
                callback(this);
            } else {
                setTimeout(checkReady, 10);
            }
        };
        checkReady();
    }
};

// Safe API wrapper that handles undefined states
window.HasJS.safeApi = {
    get: async function(url, options = {}) {
        if (window.HasJS.api && typeof window.HasJS.api.get === 'function') {
            return await window.HasJS.api.get(url, options);
        } else {
            // Fallback to native fetch
            const response = await fetch(url, { method: 'GET', ...options });
            return await response.json();
        }
    },
    
    post: async function(url, data, options = {}) {
        if (window.HasJS.api && typeof window.HasJS.api.post === 'function') {
            return await window.HasJS.api.post(url, data, options);
        } else {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', ...options.headers },
                body: JSON.stringify(data),
                ...options
            });
            return await response.json();
        }
    }
};

// Safe router wrapper
window.HasJS.safeRouter = {
    push: function(path) {
        if (window.HasJS.router && typeof window.HasJS.router.push === 'function') {
            return window.HasJS.router.push(path);
        } else {
            // Fallback to manual history management
            if (window.history && window.history.pushState) {
                window.history.pushState({path: path}, '', path);
                window.dispatchEvent(new PopStateEvent('popstate', {state: {path: path}}));
            }
        }
    },
    
    getCurrentPath: function() {
        if (window.HasJS.router && typeof window.HasJS.router.getCurrentPath === 'function') {
            return window.HasJS.router.getCurrentPath();
        } else {
            return window.location.pathname;
        }
    }
};

// Helper function for creating components
window.createComponent = (name, definition) => {
    return window.HasJS.component(name, definition);
};

// Helper function for global state
window.useGlobalState = (key, defaultValue = null) => {
    if (defaultValue !== null && window.HasJS.getState(key) === undefined) {
        window.HasJS.setState(key, defaultValue);
    }
    return [
        () => window.HasJS.getState(key),
        (value) => window.HasJS.setState(key, value)
    ];
};

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { HasJS, HasComponent, HasRouter, HasAPI };
}

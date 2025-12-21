/**
 * Email Template Editor using GrapesJS
 * A reusable component for editing email templates
 */
export class EmailTemplateEditor {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.options = {
            height: options.height || '60vh',
            initialContent: options.initialContent || '',
            onUpdate: options.onUpdate || null,
            ...options,
        };
        this.editor = null;
        this.lastContent = '';
    }

    /**
     * Initialize the editor
     */
    init() {
        const container = document.getElementById(this.containerId);
        if (!container) {
            console.error(`Container #${this.containerId} not found`);
            return;
        }

        // Check if GrapesJS is loaded
        if (typeof grapesjs === 'undefined') {
            console.error('GrapesJS is not loaded. Please include the GrapesJS library.');
            return;
        }

        // Initialize GrapesJS with newsletter preset
        this.editor = grapesjs.init({
            container: `#${this.containerId}`,
            height: this.options.height,
            width: '100%',
            plugins: ['gjs-preset-newsletter'],
            pluginsOpts: {
                'gjs-preset-newsletter': {
                    modalLabelImport: 'Importez votre template',
                    modalLabelExport: 'Exportez votre template',
                },
            },
            storageManager: {
                type: 'local',
                autosave: false,
                autoload: false,
            },
            canvas: {
                styles: [
                    'https://cdn.jsdelivr.net/npm/grapesjs@0.21.7/dist/css/grapes.min.css',
                ],
            },
        });

        // Load initial content if provided
        if (this.options.initialContent) {
            this.loadContent(this.options.initialContent);
        }

        // Setup update handler
        this.setupUpdateHandler();

        return this.editor;
    }

    /**
     * Load content into the editor
     */
    loadContent(content) {
        if (!this.editor || !content) {
            return;
        }

        try {
            // Try to parse style and HTML separately
            const styleMatch = content.match(/<style[^>]*>([\s\S]*?)<\/style>/);
            const htmlMatch = content.match(/<\/style>([\s\S]*)$/) || content.match(/^([\s\S]*)$/);

            if (styleMatch && htmlMatch) {
                this.editor.setComponents(htmlMatch[1].trim());
                this.editor.setStyle(styleMatch[1]);
            } else {
                this.editor.setComponents(content);
            }

            this.lastContent = this.getContent();
        } catch (error) {
            console.error('Error loading content into editor:', error);
            // Fallback: wrap content in a div
            this.editor.setComponents('<div>' + content + '</div>');
        }
    }

    /**
     * Get the current content from the editor
     */
    getContent() {
        if (!this.editor) {
            return '';
        }

        const html = this.editor.getHtml();
        const css = this.editor.getCss();
        return '<style>' + css + '</style>' + html;
    }

    /**
     * Setup update handler to sync with Livewire
     */
    setupUpdateHandler() {
        if (!this.editor) {
            return;
        }

        const syncToCallback = () => {
            const content = this.getContent();

            if (content !== this.lastContent) {
                this.lastContent = content;

                // Call the update callback if provided
                if (this.options.onUpdate && typeof this.options.onUpdate === 'function') {
                    this.options.onUpdate(content);
                }
            }
        };

        // Listen to various editor events
        this.editor.on('update', syncToCallback);
        this.editor.on('component:update', syncToCallback);
        this.editor.on('component:add', syncToCallback);
        this.editor.on('component:remove', syncToCallback);
        this.editor.on('style:update', syncToCallback);
    }

    /**
     * Destroy the editor instance
     */
    destroy() {
        if (this.editor) {
            try {
                this.editor.destroy();
            } catch (error) {
                console.error('Error destroying editor:', error);
            }
            this.editor = null;
        }
        this.lastContent = '';
    }

    /**
     * Get the editor instance
     */
    getEditor() {
        return this.editor;
    }
}

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Editor will be initialized manually via Livewire hooks
    });
}








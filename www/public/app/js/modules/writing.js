'use strict';

/**
 * Module to compose output JSON preview
 */
const cPreview = require('../classes/cPreview');

/**
 * Module for pages using Editor
 */
export default class Writing {

    constructor() {

        /**
         * CodeX Editor Instance
         */
        this.editor = null;

        /**
         * DOM elements
         */
        this.nodes = {
            /**
            * Container to output saved Editor data
            */
            outputWrapper: null
        };

    }

    /**
     * @typedef {Object} writingSettings - Settings for Editor on landing page
     * @property {String} writingSettings.output_id - ID of container where Editor's saved data will be shown
     * @property {function} writingSettings.onChange - Modifications callback for the Editor
     */

    /**
     * Initialization. Called by Module Dispatcher
     */
    init(writingSettings) {

        /**
         * Bind onchange callback to preview JSON data
         */
        writingSettings.onChange = () => {

            this.previewData();

        };

        this.loadEditor(writingSettings).then((editor) => {

            this.editor = editor;

            this.prepareEditor(writingSettings);

            this.preparePreview(writingSettings);

        });

    };

    /**
     * Load Editor from separate chunk
     * @param settings - settings for Editor initialization
     * @return {Promise<Editor>} - CodeX Editor promise
     */
    loadEditor(settings) {

        return import(/* webpackChunkName: "editor" */ 'classes/editor')
            .then(({default: Editor}) => {

                return new Editor(settings);

            });

    }

    /**
     * When Editor is ready, preview JSON output with initial data
     */
    prepareEditor() {

        this.editor.editor.isReady
            .then(() => {

                this.previewData();

            })
            .catch((reason) => {

                console.log(`CodeX Editor initialization failed because of ${reason}`);

            });

    };

    /**
     * Prepare node to output Editor data preview
     * @param settings - settings for Editor data output
     */
    preparePreview(settings) {

        /**
         * Define container to output Editor saved data
         * @type {HTMLElement}
         */
        this.nodes.outputWrapper = document.getElementById(settings.output_id);

        if (this.nodes.outputWrapper) {

            console.log('Output target with ID: «' + settings.output_id + '» was initialized successfully');

        } else {

            console.warn('Can\'t find output target with ID: «' + settings.output_id + '»');

        }

    }

    /**
     * Shows JSON output of editor saved data
     */
    previewData() {

        this.editor.save().then((savedData) => {

            cPreview.show(savedData, this.nodes.outputWrapper);

        });

    };

};
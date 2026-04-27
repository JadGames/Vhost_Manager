/**
 * Early-loaded theme initialization
 * Must run before body content is rendered to avoid flash of unstyled content
 */
(function() {
    'use strict';
    var THEME_KEY = 'aphost-theme';
    var theme = localStorage.getItem(THEME_KEY) || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
}());

(function() {
    'use strict';

    angular
        .module('app')
        .config(configure);

    configure.$inject = ['routerHelperProvider'];
    function configure(routerHelperProvider) {
        routerHelperProvider.configure({docTitle: 'php-htmldiff: '});
    }
})();

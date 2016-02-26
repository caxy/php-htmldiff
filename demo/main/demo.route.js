(function() {
    'use strict';

    angular
        .module('app.demo')
        .run(appRun);

    appRun.$inject = ['routerHelper'];

    function appRun(routerHelper) {
        routerHelper.configureStates(getStates());
    }

    function getStates() {
        return [
            {
                state: 'demo',
                config: {
                    url: '/',
                    templateUrl: 'main/demo.html',
                    controller: 'DemoController',
                    controllerAs: 'vm',
                    title: 'demo'
                }
            }
        ];
    }
})();

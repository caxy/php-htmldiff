(function() {
    'use strict';

    angular
        .module('app.tracker')
        .run(appRun);

    appRun.$inject = ['routerHelper'];

    function appRun(routerHelper) {
        routerHelper.configureStates(getStates());
    }

    function getStates() {
        return [
            {
                state: 'tracker',
                config: {
                    url: '/diff-tracker',
                    templateUrl: 'tracker/tracker.html',
                    controller: 'TrackerController',
                    controllerAs: 'vm',
                    title: 'tracker'
                }
            }
        ];
    }
})();

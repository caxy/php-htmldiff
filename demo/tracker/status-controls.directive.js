(function() {
    'use strict';

    angular
        .module('app.tracker')
        .directive('statusControls', statusControls);

    function statusControls() {
        var directive = {
            restrict: 'EA',
            templateUrl: 'tracker/status-controls.directive.html',
            scope: {
                diff: '=',
                setStatusHandler: '&'
            },
            controller: StatusControlsController,
            controllerAs: 'vm',
            bindToController: true
        };

        return directive;
    }

    function StatusControlsController () {
        var vm = this;


    }
})();

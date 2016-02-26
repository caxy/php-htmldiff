(function() {
    'use strict';

    angular
        .module('app.tracker')
        .factory('sseService', sseService);

    sseService.$inject = ['$rootScope'];
    function sseService($rootScope) {
        var service = {
            create: create
        };

        return service;

        function create(url, onMessage, onFinish, onError) {
            var source = new EventSource(url);

            source.addEventListener('message', getBroadcastListener('Message'), false);
            source.addEventListener('finish', getBroadcastListener('Finish'), false);
            source.addEventListener('error', getBroadcastListener('Error'), false);

            $rootScope.$on('sseMessage', onMessage);
            $rootScope.$on('sseFinish', onFinish);
            $rootScope.$on('sseError', onError);

            return source;

            function getBroadcastListener(name) {
                return function(e) {
                    $rootScope.$broadcast('sse'+name, e, this);
                }
            }
        }
    }
})();

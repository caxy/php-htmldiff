var demo = angular.module('demo', ['ngSanitize']);

demo.controller('diffCtrl', ['$scope', '$http', '$sce', '$timeout', function ($scope, $http, $sce, $timeout) {
    $scope.demos = [];
    $scope.updateDelay = 800;
    $scope.currentTimeout = null;
    $scope.loading = false;
    $scope.waiting = false;

    $scope.trustHtml = function (text) {
        return typeof text !== 'undefined' ? $sce.trustAsHtml(text) : '';
    };

    $scope.reset = function () {
        $scope.oldText = '';
        $scope.newText = '';
        $scope.diff = '';
        $scope.loading = false;
        $scope.waiting = false;
        if ($scope.currentTimeout) {
            $timeout.cancel($scope.currentTimeout);
        }
    }

    $scope.update = function () {
        if ($scope.currentTimeout) {
            $timeout.cancel($scope.currentTimeout);
        }
        $scope.currentTimeout = $timeout(function () {
            $scope.getDiff();
        }, $scope.updateDelay);

        $scope.waiting = true;
    };

    $scope.getDiff = function () {
        $scope.waiting = false;
        $scope.loading = true;
        $http.post('index.php', { oldText: $scope.oldText, newText: $scope.newText })
            .success(function (data) {
                $scope.diff = data.diff;
                $scope.loading = false;
            });
    };

    $scope.loadDemos = function () {                        
        $http.get('demo_text.php')
            .success(function (data) {
                $scope.demos = data;
            });
    };

    $scope.diffDemo = function (index) {
        if (typeof index === 'undefined') {
            index = 0;
        }

        $scope.oldText = $scope.demos[index]['old'];
        $scope.newText = $scope.demos[index]['new'];
        $scope.getDiff();
    };

    $scope.loadDemos();
}]);
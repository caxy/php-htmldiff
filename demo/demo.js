var demo = angular.module('demo', ['ngSanitize']);

demo.controller('diffCtrl', ['$scope', '$http', '$sce', '$timeout', function ($scope, $http, $sce, $timeout) {
    $scope.demos = [];
    $scope.updateDelay = 800;
    $scope.currentTimeout = null;
    $scope.loading = false;
    $scope.waiting = false;
    $scope.diffName = '';
    $scope.currentDemo = null;
    $scope.debugOutput = {};
    $scope.matchThreshold = 80;
    $scope.overrides = [];
    $scope.legislativeOverride = null;

    $scope.trustHtml = function (text) {
        return typeof text !== 'undefined' ? $sce.trustAsHtml(text) : '';
    };

    $scope.reset = function () {
        $scope.oldText = '';
        $scope.newText = '';
        $scope.diff = '';
        $scope.loading = false;
        $scope.waiting = false;
        $scope.currentDemo = null;
        $scope.legislativeOverride = null;
        if ($scope.currentTimeout) {
            $timeout.cancel($scope.currentTimeout);
        }
    };

    $scope.update = function () {
        if ($scope.currentTimeout) {
            $timeout.cancel($scope.currentTimeout);
        }
        $scope.currentTimeout = $timeout(function () {
            $scope.getDiff();
        }, $scope.updateDelay);

        $scope.waiting = true;
    };

    $scope.swapText = function () {
        var oldText = $scope.oldText;
        $scope.oldText = $scope.newText;
        $scope.newText = oldText;

        $scope.getDiff();
    };

    $scope.getDiff = function () {
        $scope.waiting = false;
        $scope.loading = true;
        $http.post('index.php', { oldText: $scope.oldText, newText: $scope.newText, matchThreshold: $scope.matchThreshold })
            .then(function (response) {
                $scope.diff = response.data.hasOwnProperty('diff') ? response.data.diff : response.data;
                $scope.loading = false;
                $scope.addDebugOutput(response.data.debug);
            })
            .catch(function (response) {
                console.error('Gists error', response.status, response.data);
            });
    };

    $scope.loadDemos = function () {                        
        $http.get('demos.json')
            .success(function (data) {
                $scope.demos = data;
            });
    };

    $scope.loadOverrides = function() {
        return $http.get('diff.json')
            .then(function (response) {
                $scope.overrides = response.data;

                return $scope.overrides;
            });
    };

    $scope.diffDemo = function (index) {
        if (typeof index === 'undefined') {
            index = 0;
        }

        $scope.oldText = $scope.demos[index]['old'];
        $scope.newText = $scope.demos[index]['new'];
        $scope.getDiff();
        $scope.currentDemo = $scope.demos[index];
        $scope.legislativeOverride = $scope.demos[index].hasOwnProperty('legislativeOverride') ? $scope.demos[index]['legislativeOverride'] : null;
    };

    $scope.diffOverride = function(override, index) {
        $scope.oldText = override.old;
        $scope.newText = override.new;
        $scope.legislativeOverride = override.override;
        $scope.getDiff();
        $scope.currentDemo = override;
        if (!$scope.currentDemo.name) {
            $scope.currentDemo.name = 'Override Demo ' + (index + 1);
        }
        $scope.currentDemo.isOverride = true;
    };

    $scope.updateDemo = function() {
        $scope.currentDemo.old = $scope.oldText;
        $scope.currentDemo.new = $scope.newText;

        return $http.post('save_demo.php', $scope.currentDemo)
            .then(function (response) {
                return response;
            });
    };

    $scope.saveNewDemo = function() {
        var newIndex = $scope.demos.length + 1;
        if ($scope.diffName.length === 0) {
            $scope.diffName = 'DEMO ' + newIndex;
        }

        var newDemo = {'old': $scope.oldText, 'new': $scope.newText, 'name': $scope.diffName, 'legislativeOverride': $scope.legislativeOverride};

        $scope.demos.push(newDemo);

        return $http.post('save_demo.php', newDemo)
            .then(function (response) {
                $scope.currentDemo = newDemo;

                return $scope.currentDemo;
            });
    };

    $scope.addDebugOutput = function(data) {
        angular.extend($scope.debugOutput, data);
    };

    $scope.loadDemos();
    $scope.loadOverrides();
}]);

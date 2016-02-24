(function() {
    'use strict';
    
    angular
        .module('demo')
        .controller('DemoController', DemoController);
    
    DemoController.$inject = ['$q', '$http', '$sce', '$timeout'];
    
    function DemoController($q, $http, $sce, $timeout) {
        var vm = this;

        vm.demos = [];
        vm.updateDelay = 800;
        vm.currentTimeout = null;
        vm.loading = false;
        vm.waiting = false;
        vm.diffName = '';
        vm.currentDemo = null;
        vm.debugOutput = {};
        vm.matchThreshold = 80;
        vm.overrides = [];
        vm.legislativeOverride = null;
        vm.tableDiffNumber = 1;
        vm.tableDiffing = true;
        vm.editorOptions = {};
        vm.ckEditorEnabled = true;

        vm.trustHtml = trustHtml;
        vm.reset = reset;
        vm.update = update;
        vm.swapText = swapText;
        vm.diffDemo = diffDemo;
        vm.diffOverride = diffOverride;
        vm.diffTableDemo = diffTableDemo;
        vm.updateDemo = updateDemo;
        vm.saveNewDemo = saveNewDemo;
        vm.toggleCkEditor = toggleCkEditor;

        activate();

        function activate() {
            var promises = [loadDemos(), loadOverrides()];
            return $q.all(promises).then(function() {

            });
        }

        function trustHtml(text) {
            return typeof text !== 'undefined' ? $sce.trustAsHtml(text) : '';
        }

        function toggleCkEditor() {
            vm.ckEditorEnabled = !vm.ckEditorEnabled;
        }

        function reset() {
            vm.oldText = '';
            vm.newText = '';
            vm.diff = '';
            vm.loading = false;
            vm.waiting = false;
            vm.currentDemo = null;
            vm.legislativeOverride = null;
            if (vm.currentTimeout) {
                $timeout.cancel(vm.currentTimeout);
            }
        }

        function update() {
            if (vm.currentTimeout) {
                $timeout.cancel(vm.currentTimeout);
            }
            vm.currentTimeout = $timeout(function () {
                getDiff();
            }, vm.updateDelay);

            vm.diff = null;
            vm.waiting = true;
        }

        function swapText() {
            var oldText = vm.oldText;
            vm.oldText = vm.newText;
            vm.newText = oldText;

            getDiff();
        }

        function diffDemo(index) {
            if (typeof index === 'undefined') {
                index = 0;
            }

            vm.oldText = vm.demos[index]['old'];
            vm.newText = vm.demos[index]['new'];
            getDiff();
            vm.currentDemo = vm.demos[index];
            vm.legislativeOverride = vm.demos[index].hasOwnProperty('legislativeOverride') ? vm.demos[index]['legislativeOverride'] : null;
        }

        function diffOverride(override, index) {
            vm.oldText = override.old;
            vm.newText = override.new;
            vm.legislativeOverride = override.override;
            getDiff();
            vm.currentDemo = override;
            if (!vm.currentDemo.name) {
                vm.currentDemo.name = 'Override Demo ' + (index + 1);
            }
            vm.currentDemo.isOverride = true;
        }

        function diffTableDemo(index) {
            loadTableDiff(index)
                .then(function(response) {
                    vm.oldText = response.data.old;
                    vm.newText = response.data.new;
                    vm.legislativeOverride = null;
                    getDiff();
                    vm.currentDemo = null;
                })
                .catch(function(e) {
                    console.log(e);
                });
        }

        function updateDemo() {
            vm.currentDemo.old = vm.oldText;
            vm.currentDemo.new = vm.newText;

            return $http.post('save_demo.php', vm.currentDemo)
                .then(function (response) {
                    return response;
                });
        }

        function saveNewDemo() {
            var newIndex = vm.demos.length + 1;
            if (vm.diffName.length === 0) {
                vm.diffName = 'DEMO ' + newIndex;
            }

            var newDemo = {'old': vm.oldText, 'new': vm.newText, 'name': vm.diffName, 'legislativeOverride': vm.legislativeOverride};

            vm.demos.push(newDemo);

            return $http.post('save_demo.php', newDemo)
                .then(function (response) {
                    vm.currentDemo = newDemo;

                    return vm.currentDemo;
                });
        }

        function loadTableDiff(index) {
            return $http({
                url: 'load_table_diff.php',
                method: 'POST',
                data: {index: index},
                header: {'Content-Type': 'application/json; charset=UTF-8'}
            });
        }

        function getDiff() {
            vm.waiting = false;
            vm.loading = true;
            vm.diff = null;
            $http.post('index.php', {
                    oldText: vm.oldText,
                    newText: vm.newText,
                    matchThreshold: vm.matchThreshold,
                    tableDiffing: vm.tableDiffing
                })
                .then(function (response) {
                    vm.diff = response.data.hasOwnProperty('diff') ? response.data.diff : response.data;
                    vm.loading = false;
                    addDebugOutput(response.data.debug);
                })
                .catch(function (response) {
                    console.error('Gists error', response.status, response.data);
                });
        }

        function loadDemos() {
            $http.get('demos.json')
                .success(function (data) {
                    vm.demos = data;
                });
        }

        function loadOverrides() {
            return $http.get('diff.json')
                .then(function (response) {
                    vm.overrides = response.data;

                    return vm.overrides;
                });
        }

        function addDebugOutput(data) {
            angular.forEach(data, function(value, key) {
                data[key] = {
                    messages: value,
                    isCollapsed: true
                };
            });

            vm.debugOutput = data;
        }
    }
})();

(function() {
    'use strict';
    
    angular
        .module('app.tracker')
        .controller('TrackerController', TrackerController);

    TrackerController.$inject = ['$q', '$http', '$sce', '$timeout', 'sseService', '$scope'];
    
    function TrackerController($q, $http, $sce, $timeout, sseService, $scope) {
        var vm = this;

        vm.statusClassMap = {
            'approved': 'primary',
            'denied': 'danger',
            'skipped': 'warning',
            'none': 'default',
            'ignored': 'default'
        };

        vm.loading = true;
        vm.reindexing = false;
        vm.index = 0;
        vm.currentDiff = null;
        vm.error = false;
        vm.errorDescription = false;
        vm.progressValue = 0;
        vm.source = null;
        vm.stats = {};
        vm.diffs = [];
        vm.offset = 0;
        vm.limit = 25;
        vm.canLoadMore = false;

        vm.trustHtml = trustHtml;
        vm.setStatus = setStatus;
        vm.reload = reload;
        vm.reindex = reindex;
        vm.getDiff = getDiff;
        vm.loadMore = loadMore;
        vm.setFavorite = setFavorite;
        vm.setStatus = setStatus;

        activate();

        function activate() {
            var promises = [cgetDiffs()];
            getStats();
            return $q.all(promises).then(function() {
                vm.loading = false;
            });
        }

        function trustHtml(text) {
            return typeof text !== 'undefined' ? $sce.trustAsHtml(text) : '';
        }

        function cgetDiffs() {
            return apiCall('cget', {limit: vm.limit, offset: vm.offset, status_filter: 1})
                .then(success);

            function success(data) {
                if (data.length <= 0) {
                    vm.canLoadMore = false;

                    return data;
                }

                vm.canLoadMore = true;

                Array.prototype.push.apply(vm.diffs, data);
                vm.offset += data.length;

                if (!vm.currentDiff) {
                    getDiff(vm.diffs[vm.index]);
                }

                return vm.diffs;
            }
        }

        function loadMore() {
            return cgetDiffs();
        }

        function getDiff(diff) {
            vm.loading = true;
            if (vm.currentDiff && vm.currentDiff._id != diff._id) {
                vm.currentDiff.active = false;
                vm.currentDiff = null;
            }

            return apiCall('get', {_id: diff._id})
                .then(success);

            function success(data) {
                var diffIndex = vm.diffs.indexOf(diff);
                vm.diffs[diffIndex] = data;
                vm.currentDiff = vm.diffs[diffIndex];
                vm.loading = false;
                vm.index = diffIndex;
                vm.currentDiff.active = true;

                return vm.currentDiff;
            }
        }

        function getStats() {
            return apiCall('stats')
                .then(success);

            function success(data) {
                angular.forEach(data, function(stat) {
                    if (!stat._id) {
                        stat._id = 'none';
                    }
                    vm.stats[stat._id] = stat.count;
                });

                return vm.stats;
            }
        }

        function setStatus(diff, status) {
            vm.diffs.splice(vm.diffs.indexOf(diff), 1);

            return apiCall('putStatus', {
                '_id': diff._id,
                'status': status,
                'notes': diff.notes
            })
                .then(success);

            function success(data) {
                var prevStatus = diff.status ? diff.status : 'none';
                diff.status = status;
                diff.active = false;

                if (!vm.stats.hasOwnProperty(status)) {
                    vm.stats[status] = 0;
                }

                vm.stats[status]++;

                if (vm.stats.hasOwnProperty(prevStatus)) {
                    vm.stats[prevStatus]--;
                }

                if (vm.diffs.length > 0 && vm.index in vm.diffs) {
                    vm.offset--;
                    getDiff(vm.diffs[vm.index]);
                } else {
                    vm.index = 0;
                    vm.offset = 0;
                    loadMore()
                        .then(function() {
                            getDiff(vm.diffs[vm.index]);
                        });
                }

                return data;
            }
        }

        function setFavorite(diff, favorite) {
            diff.favorite = favorite;

            return apiCall('favorite', {
                '_id': diff._id,
                'favorite': favorite ? 1 : 0
            })
                .then(success);

            function success(data) {
            }
        }

        function reload() {
            vm.loading = true;
            vm.currentDiff = null;
            return cgetDiffs().then(function() { vm.loading = false; });
        }

        function reindex() {
            vm.reindexing = true;
            vm.currentDiff = null;
            vm.progressValue = 0;
            vm.index = 0;

            return sseService.create('reindex_tracker_data.php', onMessage, onFinish, onError);

            function onMessage(e, response, source) {
                $scope.$apply(function() {
                    if (response.data === 'done') {
                        vm.reindexing = false;
                        source.close();
                    } else {
                        vm.progressValue = parseInt(response.data);
                    }
                });

                return vm.progressValue;
            }

            function onFinish(e, response, source) {
                $scope.$apply(function() {
                    vm.reindexing = false;
                });
                source.close();
                return reload();
            }

            function onError(e, response, source) {
                $scope.$apply(function() {
                    vm.reindexing = false;
                    vm.error = true;
                    vm.errorDescription = response;
                    source.close();
                });
            }
        }

        function apiCall(action, data) {
            data = data || {};
            data.action = action;

            return $http({
                url: 'tracker/tracker.php',
                method: 'POST',
                data: data
            })
                .then(success)
                .catch(failed);

            function success(response) {
                return response.data;
            }

            function failed(e) {
                vm.error = true;
                vm.errorDescription = e;
            }
        }
    }
})();

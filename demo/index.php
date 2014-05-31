<?php

use Caxy\HtmlDiff\HtmlDiff;

require __DIR__.'/../lib/Caxy/HtmlDiff/HtmlDiff.php';
require __DIR__.'/../lib/Caxy/HtmlDiff/Match.php';
require __DIR__.'/../lib/Caxy/HtmlDiff/Operation.php';

$input = file_get_contents('php://input');

if ($input) {
    $data = json_decode($input, true);
    $diff = new HtmlDiff($data['oldText'], $data['newText']);
    $diff->build();
    
    header('Content-Type: application/json');
    echo json_encode(array('diff' => $diff->getDifference()));
} else {
    ?>
    <html ng-app="demo">
        <head>
            <script src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.15/angular.min.js"></script>
            <script src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.15/angular-sanitize.min.js"></script>
            <style>
                .row {
                    width: 90%;
                    clear: both;
                    margin: 0 auto;
                }
                .html-edit {
                    float: left;
                    width: 45%;
                    position: relative;
                }
                .html-preview {
                    float: right;
                    width: 45%;
                    border: 1px solid #999;
                    height: 200px;
                    overflow: auto;
                    padding: 5px;
                }
                textarea {
                    width: 100%;
                    height: 210px;
                }
            </style>
        </head>
        <body>
            <div ng-controller="diffCtrl">
                <div class="controls row">
                    <button ng-click="reset()">RESET</button>
                    <span ng-repeat="demo in demos">
                        <button ng-click="diffDemo($index)">DEMO {{$index + 1}}</button>
                    </span>
                </div>
                
                <div class="row">
                    <h2>Old HTML</h2>
                    <div class="html-edit">
                        <textarea ng-model="oldText" name="old_text" ng-change="update()"></textarea>
                    </div>
                    <div class="html-preview" ng-bind-html="trustHtml(oldText)"></div>
                </div>
                <div class="row">
                    <h2>New HTML</h2>
                    <div class="html-edit">
                        <textarea ng-model="newText" name="new_text" ng-change="update()"></textarea>
                    </div>
                    <div class="html-preview" ng-bind-html="trustHtml(newText)"></div>
                </div>
                
                <div class="row">
                    <h2>Compared HTML <span ng-show="loading || waiting">- {{ loading ? 'Loading' : 'Waiting' }}...</span></h2>
                    <div class="html-edit">
                        <textarea ng-model="diff" name="diff" disabled ng-change="update()"></textarea>
                    </div>
                    <div class="html-preview" ng-bind-html="trustHtml(diff)"></div>
                </div>
            </div>

            <script type="text/javascript">
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
            </script>
        </body>
    </html>

    <?php
}

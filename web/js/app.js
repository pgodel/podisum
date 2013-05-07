var podisumApp = angular.module('podisumApp',
                [
                    'collectionServices',
                    'ngResource',
                    'ui.bootstrap'
                ]).config(function ($routeProvider, $httpProvider, $interpolateProvider) {

//            $interpolateProvider.startSymbol('[[').endSymbol(']]')

            $routeProvider.
                    when("/list", {controller: 'ListCollectionsCtrl', templateUrl: "templates/list_controllers.html"}).
                    otherwise({redirectTo: "/list"});

        });

/* controllers */


podisumApp.controller('ListCollectionsCtrl', ['$scope', 'Collection', '$timeout', function ($scope, Collection, $timeout) {
    $scope.loading = true;
    $scope.collections = [];

    $scope.loadCollections = function() {
        $scope.loading = true;
        $scope.collections = Collection.query(function() {
            $scope.loading = false;
        });

    }

    $scope.getEntryClass = function(counter, avg) {
        return counter > avg ? 'badge-important' : 'badge-info';
    }

    $scope.autoReload = function() {
        $scope.loadCollections();
        $timeout( function() {
            $scope.autoReload();
        }, 10000);

    }

    $scope.autoReload();

}]);


angular.module('collectionServices', ['ngResource']).
        factory('Collection', function ($resource) {
            return $resource('/api/collections/:collectionId', {}, {
                query: {method: 'GET', params: {collectionId: ''}, isArray: true}
            });
        });
var podisumSvc = angular.module('podisumSvc', []);
podisumSvc.factory('podisumSvc', function () {
    var s = {
        rates: [
            {delay: 1, label: '1s'},
            {delay: 5, label: '5s'},
            {delay: 10, label: '10s'},
            {delay: 60, label: '1m'},
            {delay: 3600, label: '1h'}
        ],
        autoRefresh: true
    };
    s.rate = s.rates[2];
    return s;
});


var podisumApp = angular.module('podisumApp',
        [
            'collectionServices',
            'ngResource',
            'podisumSvc',
            'ui.bootstrap'
        ]).config(function ($routeProvider, $httpProvider, $interpolateProvider) {

//            $interpolateProvider.startSymbol('[[').endSymbol(']]')

        $routeProvider.
            when("/list", {controller: 'ListCollectionsCtrl', templateUrl: "templates/list_controllers.html"}).
            otherwise({redirectTo: "/list"});

    });

/* controllers */


podisumApp.controller('RefreshCtrl', ['$scope', '$rootScope', 'podisumSvc', function ($scope, $rootScope, podisumSvc) {
    $scope.refresh = podisumSvc;
    $scope.rates = podisumSvc.rates;
    $scope.change = function () {
        $rootScope.$broadcast('refreshUpdated', {enabled: $scope.refresh.autoRefresh, rate: $scope.refresh.rate});
    }


}]);

podisumApp.controller('ListCollectionsCtrl', ['$scope', 'Collection', '$timeout', 'podisumSvc', function ($scope, Collection, $timeout, podisumSvc) {
    var timer;
    var data;

    $scope.loading = true;
    $scope.collections = [];

    $scope.getCollectionByName = function (name) {
        if ($scope.collections.length == 0) {
            return null;
        }
        for (var index in $scope.collections) {
            if ($scope.collections[index].name == name) {
                return $scope.collections[index];
            }
        }
        return null;
    }

    $scope.getSummaryByName = function (summaries, name) {
        for (var index in summaries) {
            if (index == name) {
                return summaries[index];
            }
        }
        return null;
    }

    $scope.getEntryByField = function (entries, val) {
        for (var index in entries) {
            if (entries[index].field == val) {
                return entries[index];
            }
        }
        return null;
    }

    $scope.$on('refreshUpdated', function (e, data) {
        $timeout.cancel(timer);
        if (podisumSvc.autoRefresh) {
            $scope.autoReload();
        }
    });


    $scope.loadCollections = function () {
        $scope.loading = true;
        data = Collection.query(function () {
            $scope.loading = false;

            if ($scope.collections.length == 0) {
                $scope.collections = data;
                return;
            }

            angular.forEach(data, function (collection) {
                if (!collection) {
                    return;
                }
                var coll = $scope.getCollectionByName(collection.name);
                if (null === coll) {
                    $scope.collections.push(coll);
                    return;
                }
                angular.forEach(collection.summaries, function (summary, index) {
                    var summ = $scope.getSummaryByName(coll.summaries, index);
                    if (null === summ) {
                        coll.summaries.push(summ);
                        return;
                    }

                    summ.totalUp = summ.total < summary.total;
                    summ.totalDown = summ.total > summary.total;
                    summ.total = summary.total;
                    summ.avgUp = summ.avg < summary.avg;
                    summ.avgDown = summ.avg > summary.avg;
                    summ.avg = summary.avg;
                    summ.avgmUp = summ.avgm < summary.avgm;
                    summ.avgmDown = summ.avgm > summary.avgm;
                    summ.avgm = summary.avgm;

                    angular.forEach(summary.entries, function (entry) {
                        var e = $scope.getEntryByField(summ.entries, entry.field);
                        if (null === e) {
                            entry.up = true;
                            summ.entries.push(entry);
                            return;
                        }
                        if (e.counter < entry.counter) {
                            e.counter = parseInt(entry.counter);
                            e.up = true;
                        } else {
                            e.up = false;
                        }
                    });

                    // remove non-existing entries
                    angular.forEach(summ.entries, function (entry, index) {
                        var e = $scope.getEntryByField(summary.entries, entry.field);
                        if (null === e) {
                            summ.entries.splice(index, 1);
                        }
                    });
                });
            });
        });

    }

    $scope.getEntryClass = function (counter, avg) {
        return counter > avg ? 'badge-important' : 'badge-info';
    }

    $scope.autoReload = function () {
        if (!podisumSvc.autoRefresh) {
            return;
        }

        $scope.loadCollections();
        timer = $timeout(function () {
            $scope.autoReload();
        }, podisumSvc.rate.delay * 1000);

    }

    $scope.autoReload();

}]);


angular.module('collectionServices', ['ngResource']).
    factory('Collection', function ($resource) {
        return $resource('/api/collections/:collectionId', {}, {
            query: {method: 'GET', params: {collectionId: ''}, isArray: true}
        });
    });
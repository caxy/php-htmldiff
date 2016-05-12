(function() {
    'use strict';

    angular
        .module('app.tracker')
        .constant('statuses', {
            STATUS_NONE: 'none',
            STATUS_APPROVED: 'approved',
            STATUS_IGNORED: 'ignored',
            STATUS_DENIED: 'denied',
            STATUS_SKIPPED: 'skipped',
            STATUS_CHANGED: 'changed'
        });
})();

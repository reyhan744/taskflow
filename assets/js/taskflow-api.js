/**
 * TaskFlow – REST API client.
 *
 * Thin fetch wrapper around the taskflow/v1 namespace with the WordPress
 * REST nonce (X-WP-Nonce) attached to every request. Errors are normalized
 * to { code, message, status } so components can react consistently.
 */
(function () {
        'use strict';

        var boot = window.TaskFlowBoot || {};
        var t = function (key) { return window.TaskFlowI18n.t(key); };

        /**
         * Build the final endpoint URL.
         *
         * On sites with plain permalinks, restUrl already contains "?rest_route=…"
         * – additional query arguments must then be joined with "&".
         *
         * @param {string} path Endpoint path, optionally with a query string.
         * @return {string}
         */
        function buildUrl(path) {
                if (-1 !== boot.restUrl.indexOf('?')) {
                        return boot.restUrl + String(path).replace('?', '&');
                }

                return boot.restUrl + path;
        }

        /**
         * Perform an API request.
         *
         * @param {string} path   Endpoint path after the namespace (e.g. "/tasks").
         * @param {Object} [opts] { method, data }.
         * @return {Promise<Object>} Parsed JSON response.
         */
        async function request(path, opts) {
                opts = opts || {};

                var url = buildUrl(path);
                var init = {
                        method: opts.method || 'GET',
                        credentials: 'same-origin',
                        headers: {
                                'X-WP-Nonce': boot.nonce,
                                'Content-Type': 'application/json; charset=utf-8'
                        }
                };

                if (opts.data && 'GET' !== init.method) {
                        init.body = JSON.stringify(opts.data);
                }

                var response;

                try {
                        response = await fetch(url, init);
                } catch (networkError) {
                        throw { code: 'taskflow_connection', message: t('error_connection'), status: 0 };
                }

                var payload = null;

                try {
                        payload = await response.json();
                } catch (parseError) {
                        payload = null;
                }

                if (!response.ok) {
                        var status = response.status;
                        var message = (payload && payload.message) ? payload.message : t('error_generic');

                        if (403 === status) { message = (payload && payload.message) ? payload.message : t('error_forbidden'); }
                        if (404 === status) { message = (payload && payload.message) ? payload.message : t('error_not_found'); }
                        if (!payload) { message = t('error_generic'); }

                        throw {
                                code: (payload && payload.code) || 'taskflow_error',
                                message: message,
                                status: status
                        };
                }

                return payload;
        }

        /**
         * Download an endpoint response as a file (export CSV/JSON/template).
         *
         * @param {string} path     Endpoint path.
         * @param {string} filename Suggested file name.
         */
        async function download(path, filename) {
                var response = await fetch(buildUrl(path), {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'X-WP-Nonce': boot.nonce }
                });

                if (!response.ok) {
                        throw { code: 'taskflow_error', message: t('error_generic'), status: response.status };
                }

                var blob = await response.blob();
                var link = document.createElement('a');
                var objectUrl = URL.createObjectURL(blob);

                link.href = objectUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(objectUrl);
        }

        window.TaskFlowApi = {
                request: request,
                get: function (path) { return request(path); },
                post: function (path, data) { return request(path, { method: 'POST', data: data }); },
                put: function (path, data) { return request(path, { method: 'PUT', data: data }); },
                del: function (path) { return request(path, { method: 'DELETE' }); },
                download: download
        };
})();

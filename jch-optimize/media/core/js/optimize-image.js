/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

const jchOptimizeImageApi = (function () {
    'use strict'

    let totalFiles = 0;

    let currentCnt = 0;

    let numOptimized = 0;

    let noWebpGenerated = 0;

    let status = 'success';

    let message = '';

    let connectAttempts = 0;
    const optimizeImages = function (page, api_mode) {
        let params = {};
        let cookieObj = {};

        if (jch_params === undefined || jch_params === null) {
            params.pro_downloadid = document.querySelector('input[id$=\'pro_downloadid\']').getAttribute('value');
            params.hidden_api_secret = document.querySelector('input[id$=\'hidden_api_secret\']').getAttribute('value');
            params.ignore_optimized = document.querySelector('input[name*=\'ignore_optimized\']:checked').getAttribute('value');
            params.recursive = document.querySelector('input[name*=\'recursive\']:checked').getAttribute('value');
            params.pro_api_resize_mode = document.querySelector('input[name*=\'pro_api_resize_mode\']:checked').getAttribute('value');
            params.pro_next_gen_images = document.querySelector('input[name*=\'pro_next_gen_images\']:checked').getAttribute('value');
        } else {
            params = jch_params
        }

        cookieObj.params = params;

        if (api_mode === 'manual') {
            const fileTree = document.querySelector('#file-tree-container');

            //Get root folder
            const rootFolder = fileTree.querySelector('ul.jqueryFileTree li.root > a');
            const root = rootFolder.dataset.root;
            //Get the folder in the file tree that is expanded
            const expandedDir = fileTree.querySelector('ul.jqueryFileTree ul.jqueryFileTree li.directory.expanded');

            //A directory or file must be checked
            if (document.querySelectorAll('#files-container input[type=checkbox]:checked').length <= 0) {
                alert(jch_message);

                return false;
            }

            const subDirs = document.querySelectorAll('#files-container li.directory input[type=checkbox]:checked');
            const subDirsArray = [];

            subDirs.forEach((item) => {
                subDirsArray.push(item.getAttribute('value'));
            })

            cookieObj.subdirs = subDirsArray;

            let filePack = [];
            //Iterate over each selected file in expanded directory
            document.querySelectorAll('#files-container li.file input[type=checkbox]:checked').forEach((item) => {
                //create file object
                const file = {};
                //save path of file stored in value of checkbox
                file.path = root + item.getAttribute('value');

                //Get the new width of file if entered
                const width = item.parentElement.querySelector('input[name=width]').value;

                if (width.length) {
                    file.width = width;
                }

                const height = item.parentElement.querySelector('input[name=height]').value;

                if (height.length) {
                    file.height = height;
                }

                filePack.push(file);
            });

            cookieObj.filepack = filePack;
        }

        addProgressBar();

        useWebSocket(page, cookieObj);
    }

    const useWebSocket = (page, cookieObj) => {
        if (connectAttempts > 10) {
            logMessage('Exceeded max connection attempts with WebSocket', 'error');
            reload();

            return;
        }

        const wssUrl = new URL(page);
        const wsPageUrl = new URL(page);
        const evtSrcPageUrl = new URL(page);

        let connectionTimeoutId;

        wssUrl.protocol = 'wss:';
        wssUrl.host = 'socket.jch-optimize.net:443';

        const webSocket = new WebSocket(wssUrl);

        wsPageUrl.search = wsPageUrl.search + '&evtMsg=WebSocket';
        evtSrcPageUrl.search = evtSrcPageUrl.search + '&evtMsg=EventSource';

        webSocket.onerror = () => {
            console.log('Error connecting to WebSocket server. Switching to EventSource...');
            useEventSource(evtSrcPageUrl.toString(), cookieObj);
        }

        webSocket.onopen = () => {
            console.log('Connected to WebSocket server.');
            //start server
            connectPHPWebSocketClient(wsPageUrl.toString());
            //Allow 5 seconds for the PHP client to connect
            connectionTimeoutId = setTimeout(function () {
                console.log('PHP client taking too long to connect. Switching to EventSource...')
                webSocket.close();
                useEventSource(evtSrcPageUrl.toString(), cookieObj);
            }, 5000);
        }

        webSocket.onmessage = (event) => {
            const response = JSON.parse(event.data);

            switch (response.type) {
                case 'connected':
                    console.log('PHP client connected.');
                    clearTimeout(connectionTimeoutId);
                    webSocket.send(JSON.stringify(cookieObj));
                    break;

                case 'addFileCount':
                    addFileCount(response.data);
                    break;

                case 'fileOptimized':
                    fileOptimized(response.data);
                    break;

                case 'alreadyOptimized':
                    alreadyOptimized(response.data);
                    break;

                case 'optimizationFailed':
                    optimizationFailed(response.data);
                    break;

                case 'webpGenerated':
                    webpGenerated(response.data);
                    break;

                case 'requestRejected':
                    requestRejected(response.data);
                    break;

                case 'apiError':
                    webSocket.close();
                    apiError(response.data);
                    break;

                case 'complete':
                    webSocket.close();
                    complete(response.data);
                    break;

                default:
                    defaultMessage(response.data);
            }
        }
    }

    async function connectPHPWebSocketClient(url) {
        try {
            const response = await fetch(url, {
                method: 'GET',
                mode: 'cors',
                cache: 'no-cache',
                credentials: 'same-origin'
            });
        } catch (error) {
            console.error('Error starting server', error);
        }
    }

    const useEventSource = (page, cookieObj) => {
        //Save subdirs as cookie
        document.cookie = 'jch_optimize_images_api=' + JSON.stringify(cookieObj);

        const evtSource = new EventSource(page);

        //console.log(evtSource.withCredentials);
        //console.log(evtSource.readyState);
        //console.log(evtSource.url);

        evtSource.onopen = function () {
            console.log('Connection to EventSource server opened.');
        }

        evtSource.addEventListener('error', () => {
            console.log('EventSource failed');
            logMessage('Connection aborted.', 'danger');
            reload();
        }, {once: true});

        evtSource.addEventListener('message', (e) => {
            defaultMessage(e.data);
        });

        evtSource.addEventListener('addFileCount', (e) => {
            addFileCount(e.data);
        });

        evtSource.addEventListener('fileOptimized', (e) => {
            fileOptimized(e.data);
        });

        evtSource.addEventListener('alreadyOptimized', (e) => {
            alreadyOptimized(e.data);
        });

        evtSource.addEventListener('optimizationFailed', (e) => {
            optimizationFailed(e.data);
        });

        evtSource.addEventListener('webpGenerated', (e) => {
            webpGenerated(e.data);
        });

        evtSource.addEventListener('requestRejected', (e) => {
            requestRejected(e.data);
        });

        evtSource.addEventListener('apiError', (e) => {
            evtSource.close();

            apiError(e.data);
        });

        evtSource.addEventListener('complete', (e) => {
            evtSource.close();

            complete(e.data);
        });
    }

    const defaultMessage = (data) => {
        logMessage(data, 'info');
    }

    const addFileCount = (data) => {
        totalFiles += Number.parseInt(data);
        updateProgressBar();
        updateStatusBar();
    }

    const fileOptimized = (data) => {
        currentCnt++;
        numOptimized++
        updateProgressBar();
        updateStatusBar();
        logMessage(data, 'success');
    }

    const alreadyOptimized = (data) => {
        currentCnt++;
        updateStatusBar();
        updateProgressBar();
        logMessage(data, 'secondary');
    }

    const optimizationFailed = (data) => {
        currentCnt++
        updateStatusBar();
        updateProgressBar();
        logMessage(data, 'warning');
    }

    const webpGenerated = (data) => {
        noWebpGenerated++;
        updateStatusBar();
        logMessage(data, 'primary');
    }

    const requestRejected = (data) => {
        currentCnt++;
        updateStatusBar();
        updateProgressBar();
        logMessage(data, 'danger');
    }

    const apiError = (data) => {
        status = 'fail';
        message = data;
        logMessage(data, 'danger');
        reload();
    }

    const complete = (data) => {
        logMessage('Done! Adding logs in folder ' + data, 'info');
        reload();
    }

    const reload = () => {
        logMessage('Reloading in <span id="reload-timer">10</span> seconds...', 'info');

        let reloadTimer = 10;

        const intervalFunc = () => {
            document.querySelector('span#reload-timer').textContent = (--reloadTimer).toString();

            if (reloadTimer === 0) {
                window.clearInterval(interValID);
            }
        }

        const interValID = window.setInterval(intervalFunc, 1000);

        const reload = () => {
            window.location.href = window.location.href + '&status=' + status + '&cnt=' + numOptimized + '&webp=' + noWebpGenerated + '&msg=' + encodeURIComponent(message);
        }

        window.setTimeout(reload, 10000);
    }

    const updateStatusBar = () => {
        const statusBar = document.querySelector('div#optimize-status');
        statusBar.textContent = 'Processed ' + currentCnt.toString() + ' / ' + totalFiles.toString() + ' files, ' + numOptimized.toString() + ' optimized, ' + noWebpGenerated + ' converted to WEBP format...';
    }

    const updateProgressBar = () => {
        const progressBar = document.querySelector('#progressbar');
        progressBar.setAttribute('max', totalFiles.toString());
        progressBar.setAttribute('value', currentCnt.toString());

        progressBar.textContent = Math.floor(currentCnt / totalFiles * 100) + '%';
    }

    const logMessage = (message, alertClass) => {
        const logWindow = document.querySelector('ul#optimize-log');
        const item = logWindow.appendChild(document.createElement('li'));
        item.classList.add("alert", "p-1", "my-1", "alert-" + alertClass);
        item.innerHTML = message;
        item.scrollIntoView({
            behavior: 'smooth',
            block: 'end'
        });
    }

    const addProgressBar = () => {

        try {
            const imageModal = new bootstrap.Modal('#optimize-images-modal-container', {
                backdrop: 'static', keyboard: false
            })
            imageModal.show();
        } catch (e) {
            //Try with jQuery
            jQuery('#optimize-images-modal-container').modal({
                backdrop: 'static', keyboard: false, show: true
            })
        }

        //Load progress bar with log window
        const modalBody = document.querySelector('#optimize-images-modal-container .modal-body');
        modalBody.innerHTML = '<progress id="progressbar">0%</progress> \
        <div id="optimize-status">Gathering files to optimize. Please wait...</div> \
        <div><ul id="optimize-log"></ul></div>';
    }
    return {
        optimizeImages: optimizeImages
    }
}());

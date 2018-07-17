var Promise = phast.ES6Promise;

phast.ScriptsLoader = {};

phast.ScriptsLoader.getScriptsInExecutionOrder = function (document, factory) {
    var elements = document.querySelectorAll('script[type="phast-script"]');
    var nonDeferred = [], deferred = [];
    for (var i = 0; i < elements.length; i++) {
        if (elements[i].hasAttribute('src') && elements[i].hasAttribute('defer')) {
            deferred.push(elements[i]);
        } else {
            nonDeferred.push(elements[i]);
        }
    }
    return nonDeferred.concat(deferred).map(function (element) {
        return factory.makeScriptFromElement(element);
    });
};

phast.ScriptsLoader.executeScripts = function (scripts) {

    var initializers = scripts.map(function (script) {
        return script.init();
    });

    function execNextScript() {
        if (scripts.length === 0) {
            return Promise.all(initializers)
                .catch(function () {});
        }
        return scripts
            .shift()
            .execute()
            .then(execNextScript)
            .catch(execNextScript);
    }

    return execNextScript();

};

phast.ScriptsLoader.Utilities = function (document) {

    var insertBefore = Element.prototype.insertBefore;

    this._document = document;

    function executeString(string) {
        return new Promise(function (resolve, reject) {
            try {
                // See: http://perfectionkills.com/global-eval-what-are-the-options/
                (1,eval)(string);
            } catch (e) {
                console.error("[Phast] Error in inline script:", e);
                console.log("First 100 bytes of script body:", string.substr(0, 100));
                reject(e);
            }
            resolve();
        })
    }

    function copyElement(source) {
        var copy = document.createElement(source.nodeName);
        Array.prototype.forEach.call(source.attributes, function (attr) {
            copy.setAttribute(attr.nodeName, attr.nodeValue);
        });
        return copy;
    }

    function restoreOriginals(element) {
        var shouldRemoveType = !element.hasAttribute('data-phast-original-type');
        Array.prototype
            .map.call(element.attributes, function (attr) {
                return attr.nodeName;
            })
            .forEach(function (attrName) {
                var matches = attrName.match(/^data-phast-original-(.*)/i);
                if (matches) {
                    element.setAttribute(matches[1], element.getAttribute(attrName));
                    element.removeAttribute(attrName);
                }
            });
        if (shouldRemoveType) {
            element.removeAttribute('type');
        }
    }

    function replaceElement(target, replacement) {
        return new Promise(function (resolve, reject) {
            replacement.addEventListener('load', resolve);
            replacement.addEventListener('error', reject);
            insertBefore.call(target.parentNode, replacement, target);
            target.parentNode.removeChild(target);
        });
    }

    function writeProtectAndExecuteString(sourceElement, scriptString) {
        return writeProtectAndCallback(sourceElement, function () {
            return executeString(scriptString);
        });
    }

    function writeProtectAndReplaceElement(target, replacement) {
        return writeProtectAndCallback(replacement, function () {
            return replaceElement(target, replacement);
        });
    }

    function writeProtectAndCallback(sourceElement, callback) {
        var writeBuffer = '';
        document.write = function (string) {
            writeBuffer += string;
        };
        document.writeln = function (string) {
            writeBuffer += string + '\n';
        };
        return callback()
            .then(function () {
                sourceElement.insertAdjacentHTML('afterend', writeBuffer);
            })
            .finally(function () {
                delete document.write;
                delete document.writeln;
            });
    }

    this.executeString = executeString;
    this.copyElement = copyElement;
    this.restoreOriginals = restoreOriginals;
    this.replaceElement = replaceElement;
    this.writeProtectAndExecuteString = writeProtectAndExecuteString;
    this.writeProtectAndReplaceElement = writeProtectAndReplaceElement;
};

phast.ScriptsLoader.Scripts = {};

phast.ScriptsLoader.Scripts.InlineScript = function (utils, element) {

    this._utils = utils;
    this._element = element;

    this.init = function () {
        return Promise.resolve();
    };

    this.execute = function () {
        var execString = element.textContent.replace(/\s*<!--\s*.*?\n/i, '');
        utils.restoreOriginals(element);
        return utils.writeProtectAndExecuteString(element, execString);
    };
};

phast.ScriptsLoader.Scripts.AsyncBrowserScript = function (utils, element) {

    this._utils = utils;
    this._element = element;

    this.init = function  () {
        var newElement = utils.copyElement(element);
        utils.restoreOriginals(newElement);
        return utils.replaceElement(element, newElement);
    };

    this.execute = function () {
        return Promise.resolve();
    };
};

phast.ScriptsLoader.Scripts.SyncBrowserScript = function (utils, element) {

    this._utils = utils;
    this._element = element;

    this.init = function () {
        return Promise.resolve();
    };

    this.execute = function () {
        var newElement = utils.copyElement(element);
        utils.restoreOriginals(newElement);
        return utils.writeProtectAndReplaceElement(element, newElement);
    };
};

phast.ScriptsLoader.Scripts.AsyncAJAXScript = function (utils, element, fetch, fallback) {

    this._utils = utils;
    this._element = element;
    this._fetch = fetch;
    this._fallback = fallback;

    this.init = function () {
        return fetch(element.getAttribute('src'))
            .then(function (execString) {
                utils.restoreOriginals(element);
                return utils.executeString(execString);
            })
            .catch(function () {
                return fallback.init();
            });
    };

    this.execute = function () {
        return Promise.resolve();
    };
};

phast.ScriptsLoader.Scripts.SyncAJAXScript = function (utils, element, fetch, fallback) {

    this._utils = utils;
    this._element = element;
    this._fetch = fetch;
    this._fallback = fallback;

    var promise;
    this.init = function () {
        promise = fetch(element.getAttribute('src'));
        return promise;
    };

    this.execute = function () {
        return promise
            .then(function (execString) {
                utils.restoreOriginals(element);
                utils.writeProtectAndExecuteString(element, execString);
            })
            .catch(function () {
                fallback.init();
                return fallback.execute();
            });
    };

};

phast.ScriptsLoader.Scripts.Factory = function (document, fetch) {

    var Scripts = phast.ScriptsLoader.Scripts;

    var utils = new phast.ScriptsLoader.Utilities(document);

    this.makeScriptFromElement = function (element) {
        if (isInline(element)) {
            return new Scripts.InlineScript(utils, element);
        }
        if (isProxied(element)) {
            if (isAsync(element)) {
                var fallback = new Scripts.AsyncBrowserScript(utils, element);
                return new Scripts.AsyncAJAXScript(utils, element, fetch, fallback);
            }
            fallback = new Scripts.SyncBrowserScript(utils, element);
            return new Scripts.SyncAJAXScript(utils, element, fetch, fallback);
        }

        if (isAsync(element)) {
            return new Scripts.AsyncBrowserScript(utils, element);
        }
        return new Scripts.SyncBrowserScript(utils, element);

    };

    function isInline (element) {
        return !element.hasAttribute('src', element);
    }

    function isProxied(element) {
        return element.hasAttribute('src')
               && element.hasAttribute('data-phast-original-src')
               && element.getAttribute('src') !== element.getAttribute('data-phast-original-src')
    }

    function isAsync(element) {
        return element.hasAttribute('async');
    }

};
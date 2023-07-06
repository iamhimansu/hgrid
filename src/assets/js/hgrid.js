function hGrid(id, requestUrl) {

    let hGridContainer = $(id);
    let hCurrentEditableTargetCell = undefined;

    $(document).on('dblclick', '.h-cell', function (e) {
        hCurrentEditableTargetCell = $(e.target.closest('.h-cell'));
        // console.log([e.currentTarget]||e.target.parent());
        let parent = $(e.currentTarget).find('.h-cell-data');
        let child = $(e.currentTarget).find('.h-cell-data-input');
        let checkboxParent = $(e.currentTarget).find('.hgrid-checkbox-parent');
        let checkbox = checkboxParent.find('.hgrid-checkbox');

        parent.css({
            display: 'none',
            width: '0',
            height: '0'
        });
        child.css({
            display: 'revert',
            width: '100%',
            height: '100%'
        });
        child.attr({
            disabled: null,
            readOnly: null
        });
        checkboxParent.css({
            display: 'revert',
            width: '100%',
            height: '100%'
        });
        checkbox.attr({
            disabled: null,
            readOnly: null
        });
        child.focus();
    });

    $(document).on('focusout', '.h-cell', function (e) {
        let parent = $(e.currentTarget).find('.h-cell-data');
        let child = $(e.currentTarget).find('.h-cell-data-input');
        let checkboxParent = $(e.currentTarget).find('.hgrid-checkbox-parent');
        let checkbox = checkboxParent.find('.hgrid-checkbox');

        console.log('klop', child.val());
        //

        const form = new FormData();
        let loader = undefined;

        form.append(child.data('model') + '[classToken]', child.data('classtoken'));
        form.append(child.data('model') + '[' + child.data('attribute') + ']', child.val());

        const ajaxProxy = new Proxy($.ajax, {
            apply: function (target, thisArg, argumentsList) {
                const [options] = argumentsList;
                options.beforeSend = function () {
                    loader = attachLoader(parent.parent(), loaderContainer);
                };

                options.success = function (response) {
                    const responseProxy = new Proxy(response, {
                        get: function (target, property, receiver) {
                            if (typeof target[property] === 'object' && target[property] !== null) {
                                return new Proxy(target[property], this);
                            } else {
                                return target[property];
                            }
                        },
                        set: function (target, property, value, receiver) {
                            target[property] = value;
                            return true;
                        },
                    });

                    console.log(responseProxy);
                    try {
                        if (responseProxy) {
                            for (const parentKey in responseProxy) {
                                if (responseProxy.hasOwnProperty(parentKey)) {
                                    const attributes = responseProxy[parentKey].attributes;
                                    for (const key in attributes) {
                                        if (attributes.hasOwnProperty(key)) {
                                            const value = attributes[key];
                                            const nameSelector = parentKey + '[' + key + ']';
                                            const dataModelInput = $('[name=\'' + nameSelector + '\']').text(value);
                                            const dataModelContent = $('[data-model-content=\'' + nameSelector + '\']').text(value);
                                            const parent = dataModelInput.parent('.h-cell');
                                            dataModelInput.val(value);
                                            dataModelContent.text(value);
                                            if (typeof parent !== 'undefined') {
                                                console.log(responseProxy[parentKey].rowsAffected)
                                                $(parent).data('toggle', 'popover')
                                                $(parent).data('content', responseProxy[parentKey].rowsAffected)
                                                console.log($(parent).popover('show'))
                                            }
                                        }
                                    }
                                }
                            }
                            // if (responseProxy.status === 200) {
                            //     if (responseProxy.data) {
                            //         child.val(responseProxy.data);
                            //         parent.text(responseProxy.data);
                            //     }
                            // }
                        }
                    } catch (e) {
                        console.log(e);
                    }
                };

                options.error = function (xhr, status, error) {
                    console.error(xhr, status, error);
                };

                const ajaxPromise = target.apply(thisArg, argumentsList);
                //
                ajaxPromise.always(function (e) {
                    loader.remove();
                });

                return ajaxPromise;
            },
        });

        if (!$(this).hasClass('has-error')) {
            child.css({
                display: 'none',
                width: '0',
                height: '0'
            });
            child.attr({
                disabled: 'disabled',
                readOnly: 'readonly'
            });
            parent.css({
                display: 'revert',
                width: '100%',
                height: '100%'
            });
            parent.attr({
                disabled: null,
                readOnly: null
            });
            checkboxParent.css({
                display: 'none',
                width: '0',
                height: '0'
            });
            checkbox.attr({
                disabled: 'disabled',
                readOnly: 'readonly'
            });

            ajaxProxy({
                url: requestUrl,
                method: 'post',
                data: form,
                processData: false,
                contentType: false
            });
        }
        // $(this).trigger('click');
    });

    //Added loader
    // Create loader elements
    const loaderContainer = document.createElement("div");
    loaderContainer.id = "loader";
    loaderContainer.className = "h-grid-cell-overlay";

    const dot1 = document.createElement("div");
    dot1.className = "h-grid-cell-overlay-dot";
    const dot2 = document.createElement("div");
    dot2.className = "h-grid-cell-overlay-dot";
    const dot3 = document.createElement("div");
    dot3.className = "h-grid-cell-overlay-dot";

    // Append dots to loader container
    loaderContainer.appendChild(dot1);
    loaderContainer.appendChild(dot2);
    loaderContainer.appendChild(dot3);

    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="popover"]').popover({});

    function attachLoader(el, loaderContainer) {
        let clonedLoader = loaderContainer.cloneNode(true);
        el.append(clonedLoader);
        return clonedLoader;
    }
}



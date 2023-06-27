function hGrid(requestUrl) {
    $(document).on('dblclick', '.h-cell', function (e) {
        // console.log([e.currentTarget]||e.target.parent());
        let parent = $(e.currentTarget).find('.h-cell-data');
        let child = $(e.currentTarget).find('.h-cell-data-input');
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
        child.focus();
    });
    $(document).on('focusout', '.h-cell', function (e) {

        let parent = $(e.currentTarget).find('.h-cell-data');
        let child = $(e.currentTarget).find('.h-cell-data-input');
        console.log('klop', child.val());

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

        const form = new FormData();

        form.append(child.data('model') + '[classToken]', child.data('classtoken'));
        form.append(child.data('model') + '[' + child.data('attribute') + ']', child.val());

        $.ajax({
            url: requestUrl,
            method: 'post',
            data: form,
            processData: false,
            contentType: false,
            success: function (response) {
                console.log(response);
                try {
                    if (response) {
                        for (const parentKey in response) {
                            if (response.hasOwnProperty(parentKey)) {
                                const attributes = response[parentKey].attributes;
                                for (const key in attributes) {
                                    if (attributes.hasOwnProperty(key)) {
                                        const value = attributes[key];
                                        const nameSelector = parentKey + '[' + key + ']';
                                        const dataModelInput = $('[name=\'' + nameSelector + '\']').text(value);
                                        const dataModelContent = $('[data-model-content=\'' + nameSelector + '\']').text(value);
                                        dataModelInput.val(value);
                                        dataModelContent.text(value);
                                    }
                                }
                            }
                        }
                        if (response.status === 200) {
                            if (response.data) {
                                child.val(response.data);
                                parent.text(response.data);
                            }
                        }
                    }
                } catch (e) {
                    console.log(e)
                }
            },
            error: function (xhr, status, error) {
                // Handle errors
                console.error(xhr, status, error);
            }
        });
    });
}

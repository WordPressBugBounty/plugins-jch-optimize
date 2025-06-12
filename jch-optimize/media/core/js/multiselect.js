/**
 * JCH Optimize - performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2020 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

const jchMultiselect = (
    function ($) {
        $(document).ready(function () {

            const timestamp = getTimeStamp()
            const datas = []
            //Get all the multiple select fields and iterate through each
            $('select.jch-multiselect').each(function () {
                const el = $(this)

                datas.push({
                    'id': el.attr('id'),
                    'type': el.attr('data-jch_type'),
                    'param': el.attr('data-jch_param'),
                    'group': el.attr('data-jch_group')
                })

            })

            $(document).on('change', 'input.only-one[type="checkbox"]', function () {
                if ($(this).is(':checked')) {
                    $(this).parent().parent().find('input[type="checkbox"]').not(this).prop('checked', false);
                }
            });

            const xhr = $.ajax({
                dataType: 'json',
                url: jchPlatform.jch_ajax_url_multiselect + '&_=' + timestamp,
                data: { 'data': datas },
                method: 'POST',
                success: function (response) {
                    $.each(response.data, function (id, obj) {

                        const select = $(`#${id}`)

                        $.each(obj.data, function (value, option) {
                            select.append(`<option value="${value}">${option}</option>`)
                        })

                        select.trigger('liszt:updated')
                        select.trigger('chosen:updated')
                    })
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error(`Error returned from ajax function 'getmultiselect'`)
                    console.error(`textStatus: ${textStatus}`)
                    console.error(`errorThrown: ${errorThrown}`)
                    console.warn(`response: ${jqXHR.responseText}`)
                    },
                    complete: function () {
                        //Remove all loading images
                        $('img.jch-multiselect-loading-image').each(function () {
                            $(this).remove()
                        })
                        //Show add item buttons
                        $('button.jch-multiselect-add-button').each(function () {
                            $(this).css('display', 'inline-block')
                        })
                    },
            })
    })

        function addJchOption(id)
        {
            updateSelect(id, getChosenInput(id))
        }

        function getChosenInput (id) {
            const input = $(`#${id} + .chzn-container > .chzn-choices > .search-field > input, #${id} + .chosen-container > .chosen-choices > .search-field > input`)
            let txt = input.val()

            if (txt === input.prop('defaultValue')) {
                txt = null
            }

            if (txt === null || txt === '') {
                alert('Please input an item in the box to add to the drop-down list')
                return false
            }

            return txt
        }

        function updateSelect(id, txt, value = '', selected = true)
        {
            const select = $(`#${id}`)

            if (value === '') {
                value = String(txt).replace('...', '')
            }

            const option = $('<option/>', {
                value: value,
                text: txt
            })

            if (selected) {
                option.attr('selected', 'selected')
            }

            select.append(option)

            select.trigger('liszt:updated')
            select.trigger('chosen:updated')
        }

        function addJchJsOption(id, fieldName, valueType = 'url', option1 = {}, option2 = {})
        {
            let txt = getChosenInput(id)

            if (txt !== false) {
                updateJchJsFieldset(fieldName, txt, txt, valueType, option1, option2)
            }
        }

        function removeJchJsOption(divId, fieldId)
        {
            const div = $(`#${divId}`)
            const span = $(`#${divId} > span.jch-js-excludes`)
            const input = $(`#${divId} > span.jch-js-excludes input[type="text"][readonly]`)

            updateSelect(fieldId, span.text(), input.val(), false)
            div.remove()
        }

        function updateJchJsFieldset(fieldName, value, selectText, valueType = 'url', option1 = {}, option2 = 'dontmove')
        {
            if (option1 === {}) {
                option1 = {type: "checkbox", name: "url"};
            }

            if (option2 === {}) {
                option2 = {type: "checkbox", name: "dontmove"}
            }


            //Get next index of value in fieldset
            let fieldset = $('fieldset#fieldset-' + fieldName)
            let index = fieldset.attr('data-index')
            let size = String(value).length

            const optionInput1 = getOptionInput(fieldName, index, option1, 'ieo');
            const optionInput2 = getOptionInput(fieldName, index, option2, 'dontmove')
            
            const html = `<div id="div-${fieldName}-${index}" class="jch-js-excludes-container">
                 <span class="jch-js-excludes"><span>
                     <input type="text" size="${size}" class="jch-js-excludes" readonly value="${value}"
                        name="${jchPlatform.setting_prefix}[${fieldName}][${index}][${valueType}]" />
                           ${selectText}
                     <button type="button" class="jch-multiselect-remove-button"
                         onmouseup="jchMultiselect.removeJchJsOption('div-${fieldName}-${index}', '${jchPlatform.setting_prefix}_${fieldName}')">
                     </button>
                 </span></span>
                 ${optionInput1}
                 ${optionInput2}`

            fieldset.append(html)

            //Update index
            fieldset.attr('data-index', ++index)
        }

        function getOptionInput(fieldName, index, option, suffix)
        {

            if (option.type === 'checkbox') {
                return `<span class="jch-js-${suffix}">
                <input type="checkbox" class="group${index} ${option.class}" name="${jchPlatform.setting_prefix}[${fieldName}][${index}][${option.name}]" >
                </span>`;
            }

            if (option.type === 'text') {
                return `<span class="jch-js-${suffix} has-text-input">
                <input type=text name="${jchPlatform.setting_prefix}[${fieldName}][${index}][${option.name}]" value="${option.defaultValue}">
                </span>`;
            }

            if (option.type === 'select') {
                let htmlOptions = '';
                option.htmlOptions.forEach((htmlOption) => {
                    htmlOptions += `<option value="${htmlOption.value}" ${htmlOption.selected}>${htmlOption.text}</option>`;
                })
                return `<span class="jch-js-${suffix} has-select">
                <select name="${jchPlatform.setting_prefix}[${fieldName}][${index}][${option.name}]">
                    ${htmlOptions}
                </select>
                </span>`
            }

            return '';
        }

        function appendJchJsOption(id, fieldName, params, valueType = 'url', option1 = {}, option2 = {})
        {
            if (!Object.hasOwn(params, 'selected')) {
                return false
            }

            const selectOptions = $(`#${id} option`)
            let selectText

            selectOptions.each(function () {
                if (params.selected === this.value) {
                    selectText = this.text
                    $(this).remove()

                    return false
                }
            })

            updateJchJsFieldset(fieldName, params.selected, selectText, valueType, option1, option2)

            $(`#${id}`).trigger('liszt:updated')
            $(`#${id}`).trigger('chosen:updated')
        }

        return {
            addJchOption: addJchOption,
            addJchJsOption: addJchJsOption,
            removeJchJsOption: removeJchJsOption,
            appendJchJsOption: appendJchJsOption
        }
    }
)(jQuery)

/**
 * Functions for getting/setting configuration values
 */


/**
 * Ajax query to retrieve configuration data and determine which form to display.
 * If the config file doesn't yet exist, load the configuration form.
 * If the config file does exist, load the last copy state checker form.
 */
function getConfig() {
    showLoader(true, 'Checking config file...');
    $.ajax({
        url: 'handlers/config_handler.php',
        dataType: 'json',
        contentType: false,
        processData: false,
        type: 'POST',
        success: function (data) {
            // Set page title
            document.title = data['title'];
            $('#header-title').text(data['title']);
            // If data['config'] isn't false, load last copy state form
            if (data['config']) {
                showLastCopyStateForm(true);
            }
            // Else, load config form
            else {
                showConfigForm(true);
            }
        },
        complete: function () {
            showLoader(false);
        }
        // TODO: error handling
    })
}


/**
 * Ajax query to set configuration data.
 * @param {FormData} formData The config values to set. Retrieved using getConfigFormData()
 */
function setConfig(formData) {
    showLoader(true, 'Creating configuration file...');
    // TODO: disable config form while uploading
    // Set function to 'set'
    formData.append('function', 'set');
    $.ajax({
        url: 'handlers/config_handler.php',
        dataType: 'json',
        contentType: false,
        processData: false,
        data: formData,
        type: 'POST',
        success: function (data) {
            // TODO: display confirmation message in output
            showConfigForm(false);
            showLastCopyStateForm(true);
        },
        complete: function () {
            showLoader(false);
        }
        // TODO: error handling
    });
}


/**
 * Retrieve values from config form and bundle them into a FormData object
 * @returns {FormData} FormData object with config values
 */
function getConfigFormData() {
    // TODO: throw exception if required fields are blank
    var state = $('#state-select').find(':selected').val();
    var institution = $('#institution-input').val();
    var wskey = $('#wskey-input').val();
    // Create FormData object with these values
    var formData = new FormData();
    formData.append('state', state);
    formData.append('institution', institution);
    formData.append('wskey', wskey);
    return formData;
}


/**
 * Show/hide the config form
 * @param setVisible
 */
function showConfigForm(setVisible) {
    if (setVisible) {
        $('#config-form-container').removeClass('hidden');
    }
    else {
        $('#config-form-container').addClass('hidden');
    }
}


/**
 * Show/hide the last copy state form
 * @param setVisible
 */
function showLastCopyStateForm(setVisible) {
    if (setVisible) {
        $('#last-copy-state-form-container').removeClass('hidden');
    }
    else {
        $('#last-copy-state-form-container').addClass('hidden');
    }
}


/**
 * Enable/disable submit button based on form requirements
 */
function refreshConfigSubmitButtonState() {
    // TODO: check state select
    var setDisabled = ($('#institution-input').val() === '') || ($('#wskey-input').val() === '');
    $('#config-form-submit').prop('disabled', setDisabled);
}


/* On page load */
$(function () {

    // TODO: assign listener to config form
    $('#config-form').submit(function (event) {
        event.preventDefault();
        // If required form data isn't present, display an error and return
        var formData;
        try {
            formData = getConfigFormData();
        } catch (e) {
            // Display error message
            displayError(e);
            return;
        }

        setConfig(formData);
    });

    // TODO: add listeners to inputs to refresh submit button state on change

    // Check if configuration data needs to be set
    getConfig();
});
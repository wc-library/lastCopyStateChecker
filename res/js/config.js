/**
 * Functions for getting/setting configuration values
 */

/* Variables */
// State and institution set in config file (initialized to generic values)
var configState = 'State';
var configInstitution = 'Institution';


/* Functions */

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
                // Set corresponding variables
                configState = data['config']['state'];
                configInstitution = data['config']['institution'];
                showConfigForm(false);
                showLastCopyStateForm(true);
            }
            // Else, load config form
            else {
                showLastCopyStateForm(false);
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
    // disable config form while uploading
    disableConfigForm(true);
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
            // Display confirmation message in output
            displaySuccess('Config file successfully created.');
            // Ensure that config file was set and set variables accordingly
            getConfig();
        },
        complete: function () {
            showLoader(false);
        }
        // TODO: error: display message and re-enable config form
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
    // Disable submit button if any of the required inputs are empty
    var setDisabled =
        ($('#state-select').find(':selected').val() === '') ||
        ($('#institution-input').val() === '') ||
        ($('#wskey-input').val() === '');
    $('#config-form-submit').prop('disabled', setDisabled);
}


/**
 * Disables or enables the form
 * @param {boolean} setDisabled Disable form if true, enable it if false
 */
function disableConfigForm(setDisabled) {
    $('#state-select').prop('disabled', setDisabled);
    $('#institution-input').prop('disabled', setDisabled);
    $('#wskey-input').prop('disabled', setDisabled);
    $('#config-form-submit').prop('disabled', setDisabled);
}


/* On page load */
$(function () {

    // Assign listener to config form
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

    // Add listeners to inputs to refresh submit button state on change
    $('#state-select').change(refreshConfigSubmitButtonState);
    $('#institution-input').on('change input paste', refreshConfigSubmitButtonState);
    $('#wskey-input').on('change input paste', refreshConfigSubmitButtonState);

    // Check if configuration data needs to be set
    getConfig();
});
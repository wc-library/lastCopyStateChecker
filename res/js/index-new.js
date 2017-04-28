/**
 * Scripts for index.php
 */

// TODO: rename once finished
// TODO: ensure documentation from encoding level check is updated for last copy state checker

/* Variables */

// State object representing which tab is selected on the form
var formState;

// Spinning loading icon
var loader = '<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle id="loader-circle" class="path" cx="50" cy="50" r="20" fill="none" stroke-width="3" stroke-miterlimit="10"/></svg></div>';


/* Objects */

/**
 * Prototype for state objects
 * @param {function} formRequirementCheckFunction Function to call when determining if form
 * requirements are met.
 * @param {function} getFormDataFunction Function to retrieve the input value packaged as
 * part of a FormData object.
 * @param {function} disableInputFunction Function to disable/enable the form input.
 * @constructor
 */
function StateObjectPrototype(
    formRequirementCheckFunction, getFormDataFunction, disableInputFunction) {
    this.formRequirementCheck = formRequirementCheckFunction;
    this.getFormData = getFormDataFunction;
    this.disableInput = disableInputFunction;
}

/**
 * Object representing the state where the file upload tab is selected
 * @type {StateObjectPrototype}
 */
var fileSelectStateObject =
    new StateObjectPrototype(
        fileSelected,
        getFileSelectFormData,
        disableFileSelect);

/**
 * Object representing the state where the text input tab is selected
 * @type {StateObjectPrototype}
 */
var listTextStateObject =
    new StateObjectPrototype(
        textEntered,
        getTextInputFormData,
        disableTextInput);


/* Functions */

/**
 * Get the selected file and return a FormData object with the file appended to it
 * @returns {FormData|boolean} FormData if there is a file to select. If there isn't, return
 * null and display an error.
 */
function getFileSelectFormData() {
    var fileSelectInput = $('#file-select-input');
    // Submit button is disabled if no files are selected, but just in case
    if(fileSelectInput.prop('files').length === 0) {
        // Throw error if a file isn't selected
        throw 'Please select a file.';
    }
    // Retrieve file from the input and upload
    var file = fileSelectInput.prop('files')[0];
    var formData = new FormData();
    formData.append('oclc-list', file);
    // So handler knows which type of data is being uploaded
    formData.append('type', 'file');
    return formData;
}


/**
 * Get the entered text and return a FormData object with the data appended to it
 * @returns {FormData|boolean} FormData if textarea isn't empty. If there isn't, return
 * null and display an error.
 */
function getTextInputFormData() {
    var listTextString = $('#list-text-input').val();
    // Submit button is disabled if no text is entered, but just in case
    if(listTextString === '') {
        // Throw error if no text is entered
        throw 'Please enter 1 or more OCLC numbers.';
    }
    var listTextArray = listTextString.split('\n');
    var formData = new FormData();
    formData.append('oclc-list', listTextArray);
    // So handler knows which type of data is being uploaded
    formData.append('type', 'text');
    return formData;
}


/**
 * Uploads data to be handled by server
 * @param {FormData} formData The data from the form to send to the handler
 */
function uploadData(formData) {
    // Disable form while uploading
    disableUploadForm(true);
    $.ajax({
        url: 'handlers/lastcopystate_handler.php',
        dataType: 'json',
        contentType: false,
        processData: false,
        data: formData,
        type: 'POST',
        success: function (data) {
            displayResults(data);
        },
        error: function (xhr, status, errorMessage) {
            // Get response text from server
            var data;
            try {
                data = JSON.parse(xhr.responseText);
            } catch (e) {
                data = false;
            }
            // If data['error'] doesn't exist or response text wasn't valid JSON, display the HTTP status response
            var messageString = (data && data.hasOwnProperty('error')) ? data['error'] : errorMessage;
            displayError(messageString);
        },
        complete: function () {
            disableUploadForm(false);
        }
    });
}


/**
 * Display the results of the last copy state check in #output
 * @param data Results of the last copy state check
 */
function displayResults(data) {
    // TODO: implement for last copy state checker
}


/**
 * Display spinning loading icon in an element
 * @param targetId String identifer or jQuery object of the element to display the loader in
 */
// TODO: display modal instead
function showLoader(targetId) {
    // Function accepts string representing id or jQuery object of element
    var targetElement = (targetId instanceof jQuery) ? targetId : $(targetId);
    // Clear contents of target and show loader
    targetElement.html(loader);
}


/**
 * Displays an error message in the output div
 * @param message Message to display
 */
function displayError(message) {
    var errorPanelString =
        [
            '<div class="panel panel-default">',
            '  <div class="panel-body text-danger">',
            '    <span class="glyphicon glyphicon-info-sign"></span> ' + message,
            '  </div>',
            '</div>'
        ].join('\n');
    $('#output').html(errorPanelString);
}


/**
 * Disables or enables the file select input
 * @param {boolean} setDisabled Disable input if true, enable it if false
 */
function disableFileSelect(setDisabled) {
    $('#file-select-input').prop('disabled', setDisabled);
    // Add/remove disabled class to file select button
    if (setDisabled) {
        $('#file-select-btn').addClass('disabled');
    } else {
        $('#file-select-btn').removeClass('disabled');
    }
}


/**
 * Disables or enables the list text input
 * @param {boolean} setDisabled Disable input if true, enable it if false
 */
function disableTextInput(setDisabled) {
    $('#list-text-input').prop('disabled', setDisabled);
}


/**
 * Disables or enables the form
 * @param {boolean} setDisabled Disable form if true, enable it if false
 */
function disableUploadForm(setDisabled) {
    // Disable/enable currently visible form input (prevents enabling inputs from the hidden tab)
    formState.disableInput(setDisabled);

    $('input[type="checkbox"]').prop('disabled', setDisabled);
    $('#file-select-submit').prop('disabled', setDisabled);
    $('ul.nav-tabs').find('a').prop('disabled', setDisabled);
}


/**
 * Event listener function for handling fileselect event
 * @param event The event object
 * @param numFiles Number of files selected
 * @param label Name of the file selected
 */
function onFileSelect(event, numFiles, label) {
    var input = $('#file-select-text'),
        log = numFiles > 1 ? numFiles + ' files selected' : label;
    if( input.length ) {
        input.val(log);
    }
    // refresh submit button state
    refreshSubmitButtonState();
}


/**
 * Event listener function for handling changes to the textarea
 * @param event The event object
 */
function onTextInput(event) {
    // Refresh submit button state
    refreshSubmitButtonState();
}


/**
 * Form requirement check function for #file-select-tab
 * @returns {boolean} True if a file is selected
 */
function fileSelected() {
    return $('#file-select-input').get(0).files.length > 0;
}


/**
 * Form requirement check function for #list-text-tab
 * @returns {boolean} True if textarea is not empty
 */
function textEntered() {
    return $('#list-text-input').val() !== '';
}


/**
 * Enable/disable submit button based on form requirements
 */
function refreshSubmitButtonState() {
    // disabled = false if current form requirement function returns true
    var setDisabled = !formState.formRequirementCheck();
    $('input[type=submit]').prop('disabled', setDisabled);
}


/* On page load */
$(function () {

    /* For file select styling (from https://www.abeautifulsite.net/whipping-file-inputs-into-shape-with-bootstrap-3) */
    // custom event for selecting file(s)
    $(document).on('change', ':file', function() {
        var input = $(this),
            numFiles = input.get(0).files ? input.get(0).files.length : 1,
            label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
        input.trigger('fileselect', [numFiles, label]);
    });

    // Add listeners to nav-tabs
    $('#list-text-tab').on('hidden.bs.tab shown.bs.tab', function (e) {
        // Set enabled state of #list-text-input based on which event is fired
        var hidden = e.type === 'hidden';
        var listTextInput = $('#list-text-input');
        listTextInput.prop('disabled', hidden);
        // Toggle listeners
        if (hidden) {
            listTextInput.off('change input paste', onTextInput);
        } else {
            listTextInput.on('change input paste', onTextInput);
            // Set state and refresh state of submit button
            formState = listTextStateObject;
            refreshSubmitButtonState();
        }
    });
    $('#file-select-tab').on('hidden.bs.tab shown.bs.tab', function (e) {
        // Set enabled state of #file-select-input based on which event is fired
        var hidden = e.type === 'hidden';
        var fileSeletInput = $('#file-select-input');
        fileSeletInput.prop('disabled',hidden);
        // Toggle listeners
        if (hidden) {
            fileSeletInput.off('fileselect', onFileSelect);
        } else {
            fileSeletInput.on('fileselect', onFileSelect);
            // Set state and refresh state of submit button
            formState = fileSelectStateObject;
            refreshSubmitButtonState();
        }
    });


    var lastCopyStateForm = $('#lasty-copy-state-form');
    var outputDiv = $('#output');

    // Assign listener to file upload form
    lastCopyStateForm.submit(function (event) {
        event.preventDefault();

        // If required form data isn't present, display an error and return
        var formData;
        try {
            formData = formState.getFormData();
        } catch (e) {
            // Display error message
            displayError(e);
            return;
        }

        // Show loader and send data to server
        showLoader(outputDiv);
        // Scroll to bottom of page (#output may be off-screen)
        // TODO: remove this once modal is implemented
        $('html, body').animate({scrollTop: $(document).height()-$(window).height()}, 800);

        uploadData(formData);
    });

    // #list-text-tab is selected by default, so add listener and set state on page load
    formState = listTextStateObject;
    $('#list-text-input').on('change input paste', onTextInput);
});
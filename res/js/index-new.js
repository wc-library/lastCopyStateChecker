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
    // Show loader
    showLoader(true, 'Processing list...');
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
            showLoader(false);
        }
    });
}


/**
 * Display the results of the last copy state check in #output
 * @param data Results of the last copy state check
 */
function displayResults(data) {
    // Table for results at the library
    var atLibraryTheadString = '<thead><tr><th>OCLC Number</th></tr></thead>';
    var atLibraryTableString = '<table class="table table-condensed table-striped">' + atLibraryTheadString + '<tbody>';
    // Table for flagged results
    var flaggedTheadString = '<thead><tr><th>OCLC Number</th></tr></thead>';
    var flaggedTableString = '<table class="table table-condensed table-striped">' + flaggedTheadString + '<tbody>';
    // Table for unflagged results
    var unflaggedTheadString = '<thead><tr><th>OCLC Number</th></tr></thead>';
    var unflaggedTableString = '<table class="table table-condensed table-striped">' + unflaggedTheadString + '<tbody>';
    // TODO: implement error checking server-side and display in table

    // Keep track of how many items are in each table
    var atLibraryCount = 0;
    var flaggedCount = 0;
    var unflaggedCount = 0;
    // TODO: errorCount = 0;

    // Iterate through results and add them to the table
    $.each(data['results'], function (i, item) {
        var isAtLibrary = item['flag']['at-library'];
        var isInState = item['flag']['in-state'];

        // TODO: Add to error table if there's an error and skip the below checks?
        // Add to at library table if item is at this institution
        if (isAtLibrary) {
            atLibraryCount++;
            var catalogLink = '<a href="' + item['flag']['url'] + '" target="_blank">' + item['oclc'] + '</a>';
            atLibraryTableString += '<tr><td>' + catalogLink + '</td></tr>'
        }
        // Flag if not found elsewhere in state, otherwise add to unflagged table
        if (!isInState) {
            flaggedCount++;
            flaggedTableString += '<tr><td>' + item['oclc'] + '</td></tr>';
        } else {
            unflaggedCount++;
            unflaggedTableString += '<tr><td>' + item['oclc'] + '</td></tr>';
        }
    });
    // TODO: If no results are found in a table, display a message indicating such

    atLibraryTableString += '</tbody></table>';
    flaggedTableString += '</tbody></table>';
    unflaggedTableString += '</tbody></table>';
    // TODO: errorTableString += '</tbody></table>';

    var outputDiv = $('#output');
    outputDiv.html('<h2><span class="glyphicon glyphicon-ok-sign text-success"></span> Items Processed.</h2><p>Click on the headings to see the lists of OCLC numbers.</p>');

    // Assemble at library panel and append it to output
    // TODO: make collapsible, expand by default
    var atLibraryCountBadge = ' <span class="badge">' + atLibraryCount + '</span>';
    var atLibraryPanelHeadingString = '<div class="panel-heading"><h3 class="panel-title">Entries listed as at ' + configInstitution + atLibraryCountBadge + '</h3></div>';
    var atLibraryPanelBodyString = '<div class="panel-body">' + atLibraryTableString + '</div>';
    var atLibraryPanel = '<div class="panel panel-primary">' + atLibraryPanelHeadingString +
        atLibraryPanelBodyString + '</div>';
    outputDiv.append(atLibraryPanel);

    // Assemble flaggedPanel and append to output
    var flaggedCountBadge = ' <span class="badge">' + flaggedCount + '</span>';
    var flaggedPanelTitleString = '<h3 class="panel-title collapse-toggle" id="flagged-collapse-toggle" data-toggle="collapse" href="#flagged-collapse">Flagged OCLCs ' + flaggedCountBadge + '</h3>';
    var flaggedPanelHeadingString = '<div class="panel-heading">' + flaggedPanelTitleString + '</div>';
    var flaggedPanelBody = $('<div id="flagged-collapse" class="panel-collapse collapse"></div>');
    flaggedPanelBody.html('<div class="panel-body">' + flaggedTableString + '</div>');
    // Rotate collapse chevron in #flagged-collapse-toggle when div is collapsing/expanding
    flaggedPanelBody.on('show.bs.collapse hide.bs.collapse', function () {
        $('#flagged-collapse-toggle').toggleClass('expanded');
    });
    var flaggedPanel = $('<div class="panel panel-warning"></div>');
    flaggedPanel.append(flaggedPanelHeadingString, flaggedPanelBody);
    outputDiv.append('<hr>', flaggedPanel);

    // Assemble unflaggedPanel and append to output
    var unflaggedCountBadge = ' <span class="badge">' + unflaggedCount + '</span>';
    var unflaggedPanelTitleString = '<h3 class="panel-title collapse-toggle" id="unflagged-collapse-toggle" data-toggle="collapse" href="#unflagged-collapse">Unflagged OCLCs ' + unflaggedCountBadge + '</h3>';
    var unflaggedPanelHeadingString = '<div class="panel-heading">' + unflaggedPanelTitleString + '</div>';
    var unflaggedPanelBody = $('<div id="unflagged-collapse" class="panel-collapse collapse"></div>');
    unflaggedPanelBody.html('<div class="panel-body">' + unflaggedTableString + '</div>');
    // Rotate collapse chevron in #unflagged-collapse-toggle when div is collapsing/expanding
    unflaggedPanelBody.on('show.bs.collapse hide.bs.collapse', function () {
        $('#unflagged-collapse-toggle').toggleClass('expanded');
    });
    var unflaggedPanel = $('<div class="panel panel-info"></div>');
    unflaggedPanel.append(unflaggedPanelHeadingString, unflaggedPanelBody);
    outputDiv.append('<hr>', unflaggedPanel);

    // TODO: Assemble errorPanel and append to output


}


/**
 * Display spinning loading icon in an element
 * @param {boolean} setVisible True = show loading dialog, false = hide loading dialog
 * @param {string} [loadingMessage] Message to display below loader
 */
function showLoader(setVisible, loadingMessage) {
    if (setVisible) {
        loadingMessage = loadingMessage || 'Loading...';
        var options = {
            backdrop: 'static',
            keyboard: false,
            show: true
        };
        // Set loading message
        $('#loading-dialog-message').text(loadingMessage);
        // Toggle modal display
        $('#loading-dialog').modal(options);
    }
    else {
        $('#loading-dialog').modal('hide');
    }
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
 * Displays a success message in the output div
 * @param message Message to display
 */
function displaySuccess(message) {
    var successPanelString =
        [
            '<div class="panel panel-default">',
            '  <div class="panel-body text-success">',
            '    <span class="glyphicon glyphicon-ok-sign"></span> ' + message,
            '  </div>',
            '</div>'
        ].join('\n');
    $('#output').html(successPanelString);
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
    $('#last-copy-state-submit').prop('disabled', setDisabled);
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
    $('#last-copy-state-submit').prop('disabled', setDisabled);
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

        uploadData(formData);
    });

    // #list-text-tab is selected by default, so add listener and set state on page load
    formState = listTextStateObject;
    $('#list-text-input').on('change input paste', onTextInput);

    // TODO: detach both forms?
});
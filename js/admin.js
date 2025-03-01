/**
 * Admin JavaScript for LZA Class Manager
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('Admin JS loaded, initializing editor...');
        
        if (typeof wp === 'undefined') {
            console.error('WordPress object not available');
            return;
        }
        
        if (typeof wp.codeEditor === 'undefined') {
            console.error('WordPress code editor not available');
            return;
        }
        
        // Get the textarea element
        var $textarea = $('#lza_custom_css');
        if (!$textarea.length) {
            console.error('Textarea #lza_custom_css not found');
            return;
        }
        
        // Check settings
        if (typeof lzaEditorSettings === 'undefined') {
            console.error('Editor settings not available');
            return;
        }
        
        try {
            // Simple initialization
            var editor = wp.codeEditor.initialize($textarea, lzaEditorSettings.settings);
            var cm = editor.codemirror;
            
            // Make CodeMirror instance available globally for the CSS variable sidebar
            window.lzaCodeMirror = cm;
            
            // Add theme selector
            setTimeout(function() {
                addThemeSelector(cm);
            }, 200);
            
            // Focus the editor
            setTimeout(function() {
                cm.refresh();
                cm.focus();
            }, 300);
            
            // Remove any existing handlers before adding new ones
            $('.lza-css-variable').off('click');
            
            // Add handler for clicking on CSS variables
            $('.lza-css-variable').on('click', function() {
                var variable = $(this).data('variable');
                if (cm) {
                    // Insert at cursor position
                    var doc = cm.getDoc();
                    var cursor = doc.getCursor();
                    doc.replaceRange('var(' + variable + ')', cursor);
                    
                    // Focus the editor
                    cm.focus();
                    
                    // Show visual feedback
                    $(this).addClass('inserted');
                    setTimeout(function() {
                        $('.lza-css-variable').removeClass('inserted');
                    }, 500);
                }
            });
            
            // Add filter for CSS variables
            var $filterInput = $('<input type="text" class="lza-filter-variables" placeholder="Filter variables..." />');
            $('.lza-variables-header').append($filterInput);
            
            $filterInput.on('input', function() {
                var filter = $(this).val().toLowerCase();
                
                $('.lza-css-variable').each(function() {
                    var variableName = $(this).text().toLowerCase();
                    if (variableName.includes(filter)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            
        } catch (e) {
            console.error('Error initializing code editor:', e);
        }
    });
    
    /**
     * Add theme selector to the editor
     */
    function addThemeSelector(cm) {
        if (!cm) {
            console.error('CodeMirror instance not available');
            return;
        }
        
        var $editorContainer = $('.lza-css-editor-container');
        if (!$editorContainer.length) {
            console.error('Editor container not found');
            return;
        }
        
        // Create theme selector
        var $editorHeader = $('.lza-editor-header');
        var $editorTitle = $editorHeader.find('h2');
        var $editorActions = $editorHeader.find('.lza-editor-actions');
        
        // Create theme selector elements
        var $themeSelector = $('<div class="lza-theme-selector"></div>');
        var $themeLabel = $('<label for="editor-theme">Theme: </label>');
        var $themeSelect = $('<select id="editor-theme"></select>');
        
        // Add theme options
        if (lzaEditorSettings && lzaEditorSettings.themes) {
            $.each(lzaEditorSettings.themes, function(value, label) {
                var $option = $('<option></option>').val(value).text(label);
                if (lzaEditorSettings.currentTheme && value === lzaEditorSettings.currentTheme) {
                    $option.attr('selected', 'selected');
                }
                $themeSelect.append($option);
            });
        }
        
        // Assemble theme selector
        $themeSelector.append($themeLabel).append($themeSelect);
        
        // Clear the header and recreate with the right structure
        $editorHeader.empty();
        $editorHeader.append($editorTitle);
        $editorHeader.append($themeSelector);
        $editorHeader.append($editorActions);
        
        // Handle theme change
        $themeSelect.on('change', function() {
            var newTheme = $(this).val();
            
            try {
                // Update theme
                cm.setOption('theme', newTheme);
                
                // Save via AJAX
                if (lzaEditorSettings && lzaEditorSettings.ajaxUrl) {
                    $.ajax({
                        url: lzaEditorSettings.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'lza_save_editor_theme',
                            theme: newTheme,
                            nonce: lzaEditorSettings.nonce
                        },
                        success: function(response) {
                            console.log('Theme saved:', newTheme);
                        }
                    });
                }
            } catch (e) {
                console.error('Error changing theme:', e);
            }
        });
        
        // Add indicator for unsaved changes
        var $autoSaveNotice = $('<div class="lza-auto-save-notice">Changes not saved</div>').insertAfter($editorActions);
        $autoSaveNotice.hide();
        
        cm.on('change', function() {
            $autoSaveNotice.show().text('Changes not saved');
        });
        
        // Form submission handler
        $('form').on('submit', function() {
            $('#lza_custom_css').val(cm.getValue());
            $autoSaveNotice.text('Saving...');
            return true;
        });
    }
    
})(jQuery);

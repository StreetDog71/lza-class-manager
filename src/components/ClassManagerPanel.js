/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { PanelBody, TextControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ClassList from './ClassList';

/**
 * Class Manager Panel Component
 */
const ClassManagerPanel = ({ clientId }) => {
    // State
    const [classInput, setClassInput] = useState('');
    const [suggestions, setSuggestions] = useState([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const [selectedSuggestion, setSelectedSuggestion] = useState(-1);
    
    // Get block attributes
    const { attributes, availableClasses } = useSelect((select) => {
        const { getBlockAttributes } = select('core/block-editor');
        const blockAttrs = getBlockAttributes(clientId) || {};
        
        return {
            attributes: blockAttrs,
            availableClasses: window.lzaClassManager?.availableClasses || []
        };
    }, [clientId]);
    
    // Get the dispatch functions
    const { updateBlockAttributes } = useDispatch('core/block-editor');
    
    // Parse existing classes
    const className = attributes.className || '';
    const existingClasses = className.split(' ').filter(Boolean);
    
    // Handle class input change
    const handleClassInputChange = (value) => {
        setClassInput(value);
        
        // Show suggestions if input has content
        if (value) {
            // Filter available classes that match the input
            const matchedClasses = availableClasses.filter(cls => 
                cls.toLowerCase().includes(value.toLowerCase()) && 
                !existingClasses.includes(cls)
            );
            
            setSuggestions(matchedClasses);
            setShowSuggestions(matchedClasses.length > 0);
            setSelectedSuggestion(-1);
        } else {
            setShowSuggestions(false);
            setSelectedSuggestion(-1);
        }
    };
    
    // Add a class
    const addClass = (classToAdd) => {
        if (!classToAdd || existingClasses.includes(classToAdd)) {
            return;
        }
        
        // Add the new class
        const newClasses = [...existingClasses, classToAdd];
        updateBlockAttributes(clientId, { className: newClasses.join(' ') });
        
        // Reset input and suggestions
        setClassInput('');
        setShowSuggestions(false);
    };
    
    // Remove a class
    const removeClass = (classToRemove) => {
        const newClasses = existingClasses.filter(cls => cls !== classToRemove);
        updateBlockAttributes(clientId, { className: newClasses.join(' ') });
    };
    
    // Reorder classes
    const reorderClasses = (reorderedClasses) => {
        updateBlockAttributes(clientId, { className: reorderedClasses.join(' ') });
    };
    
    // Handle key down for keyboard navigation and selection
    const handleKeyDown = (e) => {
        // Enter key - add class or select suggestion
        if (e.key === 'Enter') {
            e.preventDefault();
            
            if (selectedSuggestion >= 0 && selectedSuggestion < suggestions.length) {
                // Add selected suggestion
                addClass(suggestions[selectedSuggestion]);
            } else if (classInput) {
                // Add current input
                addClass(classInput);
            }
        }
        // Arrow down - navigate suggestions
        else if (e.key === 'ArrowDown' && showSuggestions) {
            e.preventDefault();
            setSelectedSuggestion(prev => 
                prev < suggestions.length - 1 ? prev + 1 : 0
            );
        }
        // Arrow up - navigate suggestions
        else if (e.key === 'ArrowUp' && showSuggestions) {
            e.preventDefault();
            setSelectedSuggestion(prev => 
                prev > 0 ? prev - 1 : suggestions.length - 1
            );
        }
        // Escape - close suggestions
        else if (e.key === 'Escape') {
            e.preventDefault();
            setShowSuggestions(false);
            setSelectedSuggestion(-1);
        }
    };
    
    return (
        <PanelBody title={__('LZA Class Manager')} initialOpen={true}>
            <div className="lza-class-manager">
                <div className="lza-class-form">
                    <TextControl
                        label={__('Add Class(es)')}
                        value={classInput}
                        onChange={handleClassInputChange}
                        onKeyDown={handleKeyDown}
                        placeholder={__('Enter class name and press Enter')}
                        className="lza-class-input"
                        autoComplete="off"
                    />
                </div>
                
                {showSuggestions && (
                    <div className="class-suggestions">
                        {suggestions.map((suggestion, index) => (
                            <div 
                                key={suggestion} 
                                className={`class-suggestion-item ${selectedSuggestion === index ? 'is-selected' : ''}`}
                                onClick={() => addClass(suggestion)}
                            >
                                {suggestion}
                            </div>
                        ))}
                    </div>
                )}
                
                <div className="lza-applied-classes">
                    <ClassList 
                        classes={existingClasses}
                        onRemove={removeClass}
                        onReorder={reorderClasses}
                    />
                </div>
            </div>
        </PanelBody>
    );
};

export default ClassManagerPanel;

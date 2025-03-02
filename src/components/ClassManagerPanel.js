/**
 * WordPress dependencies
 */
import { useState, useCallback, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';

/**
 * External dependencies
 */
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';

/**
 * Internal dependencies
 */
import ClassItem from './ClassItem';

/**
 * Class Manager Panel Component
 * 
 * Manages the classes for the selected block in the editor.
 */
const ClassManagerPanel = ({ blockProps }) => {
    const [inputValue, setInputValue] = useState('');
    const [suggestions, setShowSuggestions] = useState(false);
    const [activeSuggestion, setActiveSuggestion] = useState(0);

    // Get block attributes and setAttributes function from blockProps
    const { attributes, setAttributes } = blockProps;
    const { className = '' } = attributes;
    const availableClasses = window.lzaClassManager?.availableClasses || [];

    // Extract classes from the current block
    const getClasses = useCallback(() => {
        return className ? className.split(' ').filter(Boolean) : [];
    }, [className]);

    // Current list of classes
    const [classes, setClassesList] = useState(getClasses());

    // Update classes when block attributes change
    useEffect(() => {
        setClassesList(getClasses());
    }, [className, getClasses]);

    // Set classes back to the block
    const setClasses = useCallback((newClasses) => {
        const newClassName = newClasses.join(' ');
        setAttributes({ className: newClassName });
    }, [setAttributes]);

    // Add a class to the list
    const addClass = useCallback((classToAdd) => {
        if (!classToAdd || classes.includes(classToAdd)) return;
        
        const newClasses = [...classes, classToAdd];
        setClassesList(newClasses);
        setClasses(newClasses);
        setInputValue('');
    }, [classes, setClasses]);

    // Remove a class from the list
    const removeClass = useCallback((classToRemove) => {
        const newClasses = classes.filter(item => item !== classToRemove);
        setClassesList(newClasses);
        setClasses(newClasses);
    }, [classes, setClasses]);

    // Handle input change for the class field
    const handleInputChange = (value) => {
        setInputValue(value);
        setShowSuggestions(!!value);
        setActiveSuggestion(0);
    };

    // Handle suggestion selection
    const selectSuggestion = (suggestion) => {
        addClass(suggestion);
        setShowSuggestions(false);
    };

    // Handle key navigation in suggestions
    const handleKeyDown = (e) => {
        // Only consider suggestions if there are some
        const filteredSuggestions = availableClasses.filter(
            cls => cls.toLowerCase().includes(inputValue.toLowerCase())
        );
        
        if (!filteredSuggestions.length) return;

        // Handle keyboard navigation
        switch (e.key) {
            case 'Enter':
                e.preventDefault();
                if (suggestions && filteredSuggestions[activeSuggestion]) {
                    selectSuggestion(filteredSuggestions[activeSuggestion]);
                } else if (inputValue) {
                    addClass(inputValue);
                }
                break;
            case 'ArrowUp':
                e.preventDefault();
                setActiveSuggestion(
                    activeSuggestion > 0 ? activeSuggestion - 1 : filteredSuggestions.length - 1
                );
                break;
            case 'ArrowDown':
                e.preventDefault();
                // Show suggestions if they're hidden and we press down
                if (!suggestions) {
                    setShowSuggestions(true);
                }
                setActiveSuggestion(
                    activeSuggestion < filteredSuggestions.length - 1 ? activeSuggestion + 1 : 0
                );
                break;
            case 'Escape':
                setShowSuggestions(false);
                break;
            case 'Tab':
                // If suggestions are shown, select the current one
                if (suggestions && filteredSuggestions[activeSuggestion]) {
                    e.preventDefault();
                    selectSuggestion(filteredSuggestions[activeSuggestion]);
                }
                break;
        }
    };

    // Handle drag and drop reordering
    const handleDragEnd = (result) => {
        if (!result.destination) return;

        const reorderedClasses = Array.from(classes);
        const [movedItem] = reorderedClasses.splice(result.source.index, 1);
        reorderedClasses.splice(result.destination.index, 0, movedItem);
        
        setClassesList(reorderedClasses);
        setClasses(reorderedClasses);
    };

    // Filtered suggestions based on input
    const filteredSuggestions = inputValue ? availableClasses.filter(
        cls => cls.toLowerCase().includes(inputValue.toLowerCase())
    ) : [];

    return (
        <div className="lza-class-manager">
            <div className="lza-class-input-container">
                <TextControl
                    label={__('Add class', 'lza-class-manager')}
                    value={inputValue}
                    onChange={handleInputChange}
                    onKeyDown={handleKeyDown}
                    placeholder={__('Type class name...', 'lza-class-manager')}
                    className="lza-class-input"
                    __nextHasNoMarginBottom={true}
                    autoComplete="off"
                />
                
                {/* Suggestions dropdown */}
                {suggestions && filteredSuggestions.length > 0 && (
                    <div className="class-suggestions">
                        {filteredSuggestions.map((suggestion, index) => (
                            <div
                                key={suggestion}
                                className={`class-suggestion-item ${index === activeSuggestion ? 'is-selected' : ''}`}
                                onClick={() => selectSuggestion(suggestion)}
                                ref={index === activeSuggestion ? (el) => { 
                                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                } : null}
                            >
                                {suggestion}
                            </div>
                        ))}
                    </div>
                )}
            </div>
            
            {/* Class list with drag and drop */}
            <div className="lza-class-container">
                {classes.length > 0 ? (
                    <DragDropContext onDragEnd={handleDragEnd}>
                        <Droppable droppableId="class-list" direction="horizontal">
                            {(provided) => (
                                <div 
                                    className="lza-class-list"
                                    {...provided.droppableProps}
                                    ref={provided.innerRef}
                                >
                                    {classes.map((className, index) => (
                                        <Draggable 
                                            key={className} 
                                            draggableId={className} 
                                            index={index}
                                        >
                                            {(provided, snapshot) => (
                                                <ClassItem
                                                    className={className}
                                                    onRemove={removeClass}
                                                    provided={provided}
                                                    isDragging={snapshot.isDragging}
                                                />
                                            )}
                                        </Draggable>
                                    ))}
                                    {provided.placeholder}
                                </div>
                            )}
                        </Droppable>
                    </DragDropContext>
                ) : (
                    <p className="lza-no-classes">{__('No classes added to this block yet', 'lza-class-manager')}</p>
                )}
            </div>
        </div>
    );
};

export default ClassManagerPanel;

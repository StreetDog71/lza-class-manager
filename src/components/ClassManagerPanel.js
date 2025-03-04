/**
 * WordPress dependencies
 */
import { useState, useCallback, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';
import { select, dispatch } from '@wordpress/data';

/**
 * External dependencies
 */
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';

/**
 * Internal dependencies
 */
import ClassItem from './ClassItem';
import { 
    applyPreviewToBlock,
    removePreviewFromBlock,
    initializePreviewPreservation
} from '../utils/block-preview';

// Simple log helper - can be disabled in production
const log = process.env.NODE_ENV === 'development' ? 
    (message, ...args) => console.log(`[ClassManagerPanel] ${message}`, ...args) : 
    () => {};

// Track classes that have been permanently added during this session
if (!window._lzaPermanentlyAddedClasses) {
    window._lzaPermanentlyAddedClasses = {};
}

/**
 * Class Manager Panel Component - Completely restructured to avoid React hook issues
 */
const ClassManagerPanel = ({ blockProps }) => {
    // ========== STATE DEFINITIONS ==========
    const [inputValue, setInputValue] = useState('');
    const [suggestions, setShowSuggestions] = useState(false);
    const [activeSuggestion, setActiveSuggestion] = useState(-1);
    const [previewClass, setPreviewClass] = useState(null);
    const lastActionRef = useRef(null);
    const previewTimeoutRef = useRef(null);
    const classBeingAdded = useRef(null);

    const { attributes, setAttributes, clientId } = blockProps;
    const { className = '' } = attributes;
    const availableClasses = window.lzaClassManager?.availableClasses || [];

    // ========== UTILITY FUNCTIONS ==========
    // These don't depend on any other functions
    
    const getClasses = useCallback(() => {
        return className ? className.split(' ').filter(Boolean) : [];
    }, [className]);

    const [classes, setClassesList] = useState(getClasses());
    
    const setClasses = useCallback((newClasses) => {
        const newClassName = newClasses.join(' ');
        setAttributes({ className: newClassName });
    }, [setAttributes]);
    
    const clearPreviewTimeouts = useCallback(() => {
        if (previewTimeoutRef.current) {
            clearTimeout(previewTimeoutRef.current);
            previewTimeoutRef.current = null;
        }
    }, []);

    // ========== CLASS OPERATIONS ==========
    
    // Direct add - improved to remember added classes
    const addDirectly = useCallback((classToAdd) => {
        if (!classToAdd) return;
        
        log(`DIRECT ADD: Adding class "${classToAdd}" to block ${clientId}`);
        
        try {
            const blockEditor = select('core/block-editor');
            const block = blockEditor.getBlock(clientId);
            
            if (!block) {
                log('Block not found for direct add');
                return;
            }
            
            // Get current attribute classes - but be smarter about filtering out previews
            const attributeClasses = block.attributes.className ? 
                block.attributes.className.split(' ').filter(Boolean) : [];
                
            // Get the list of known preview classes
            const previewClasses = window._lzaPreviewClasses || [];
            
            // Get the list of classes we've already added permanently
            if (!window._lzaPermanentlyAddedClasses[clientId]) {
                window._lzaPermanentlyAddedClasses[clientId] = [];
            }
            
            // Filter out any preview classes to get the true permanent classes
            // BUT make sure to keep any classes we've added permanently during this session
            const permanentClasses = attributeClasses.filter(cls => 
                !previewClasses.includes(cls) || 
                window._lzaPermanentlyAddedClasses[clientId].includes(cls)
            );
            
            log(`All attribute classes: [${attributeClasses.join(', ')}]`);
            log(`Known preview classes: [${previewClasses.join(', ')}]`);
            log(`Previously added classes: [${window._lzaPermanentlyAddedClasses[clientId].join(', ')}]`);
            log(`True permanent classes: [${permanentClasses.join(', ')}]`);
            
            // Check if class already exists in PERMANENT classes only
            if (permanentClasses.includes(classToAdd)) {
                log(`Class "${classToAdd}" already exists as a permanent class - not adding again`);
                return;
            }
            
            // Class is not in permanent classes, so add it
            log(`Class "${classToAdd}" NOT found in permanent classes - adding it`);
            
            // Add class to permanent attributes (keeping any existing ones)
            const newClasses = [...permanentClasses, classToAdd];
            const newClassName = newClasses.join(' ');
            
            log(`Setting className to: "${newClassName}"`);
            
            // Track this as permanently added for this session
            window._lzaPermanentlyAddedClasses[clientId].push(classToAdd);
            
            // If this was a preview class, remove it from the preview classes list
            if (previewClasses.includes(classToAdd)) {
                window._lzaPreviewClasses = previewClasses.filter(cls => cls !== classToAdd);
                log(`Removed "${classToAdd}" from preview classes list`);
            }
            
            // Protect this class from being removed
            window._lzaClassesBeingAdded = window._lzaClassesBeingAdded || {};
            window._lzaClassesBeingAdded[clientId] = classToAdd;
            
            // Update block attribute, replacing any classes including previews
            dispatch('core/block-editor').updateBlockAttributes(
                clientId, 
                { className: newClassName }
            );
            
            // Update local state to match
            setClassesList(newClasses);
            
            // Clear the protection flag after a delay
            setTimeout(() => {
                if (window._lzaClassesBeingAdded && 
                    window._lzaClassesBeingAdded[clientId] === classToAdd) {
                    delete window._lzaClassesBeingAdded[clientId];
                    log(`Removed protection for "${classToAdd}"`);
                }
            }, 500);
            
            // Clear input and suggestions
            setInputValue('');
            setShowSuggestions(false);
            
            log(`Successfully added class "${classToAdd}" permanently`);
        } catch (e) {
            log('Error adding class directly:', e);
        }
    }, [clientId, setClassesList]);
    
    // Preview removal - depends only on clearPreviewTimeouts
    const removePreview = useCallback(() => {
        log('Removing preview class:', previewClass);
        
        // Don't remove if being added permanently
        if (previewClass && previewClass === classBeingAdded.current) {
            log(`Skipping removal - class is being added permanently`);
            return;
        }
        
        // Clear any pending timeouts
        clearPreviewTimeouts();
        
        // Remove from DOM
        if (previewClass) {
            removePreviewFromBlock(clientId, previewClass);
            setPreviewClass(null);
        }
    }, [previewClass, clientId, clearPreviewTimeouts]);
    
    // Apply preview - depends on removePreview
    const applyPreview = useCallback((classToPreview) => {
        if (!classToPreview || classes.includes(classToPreview)) {
            log(`Skipping preview - class already exists or invalid`);
            return;
        }
        
        // Remove existing preview first
        if (previewClass && previewClass !== classToPreview) {
            removePreviewFromBlock(clientId, previewClass);
        }
        
        // Apply new preview
        const success = applyPreviewToBlock(clientId, classToPreview);
        
        if (success) {
            setPreviewClass(classToPreview);
            log(`Preview applied: ${classToPreview}`);
        }
    }, [classes, previewClass, clientId]);
    
    // Remove class - simple function
    const removeClass = useCallback((classToRemove) => {
        const newClasses = getClasses().filter(c => c !== classToRemove);
        setClasses(newClasses);
        setClassesList(newClasses);
    }, [getClasses, setClasses]);
    
    // Add class normally - modified to track permanently added classes
    const addClass = useCallback((classToAdd) => {
        if (!classToAdd) return;
        
        // Mark as being added
        classBeingAdded.current = classToAdd;
        
        // Track if it was being previewed
        const wasPreview = classToAdd === previewClass;
        
        // Clear any preview first
        if (previewClass) {
            removePreviewFromBlock(clientId, previewClass);
            setPreviewClass(null);
        }
        
        // Get fresh classes
        const currentClasses = getClasses();
        
        // Skip if already exists (unless it was just a preview)
        if (!wasPreview && currentClasses.includes(classToAdd)) {
            log(`Class already exists: ${classToAdd}`);
            classBeingAdded.current = null;
            setInputValue('');
            setShowSuggestions(false);
            return;
        }
        
        // Add the class
        const newClasses = [...currentClasses];
        if (!newClasses.includes(classToAdd)) {
            newClasses.push(classToAdd);
        }
        
        try {
            // Track this class as permanently added for this session
            if (!window._lzaPermanentlyAddedClasses[clientId]) {
                window._lzaPermanentlyAddedClasses[clientId] = [];
            }
            window._lzaPermanentlyAddedClasses[clientId].push(classToAdd);
            
            // If this was a preview class, remove it from preview tracking
            if (window._lzaPreviewClasses && window._lzaPreviewClasses.includes(classToAdd)) {
                window._lzaPreviewClasses = window._lzaPreviewClasses.filter(cls => cls !== classToAdd);
                log(`Removed "${classToAdd}" from preview classes list`);
            }
            
            // Update attributes
            setAttributes({ className: newClasses.join(' ') });
            
            // Update state
            setClassesList(newClasses);
            
            // Reset flag after delay
            setTimeout(() => {
                classBeingAdded.current = null;
            }, 100);
            
            // Clear inputs
            setInputValue('');
            setShowSuggestions(false);
            
            log(`Added class: ${classToAdd}`);
        } catch (e) {
            log(`Error adding class: ${e.message}`);
            classBeingAdded.current = null;
        }
    }, [clientId, getClasses, previewClass, setAttributes]);

    // ========== UI HANDLERS ==========
    
    // Preferred way to add a class - handles previewed classes specially
    const selectClass = useCallback((className) => {
        log(`Selecting class: ${className}`);
        
        if (className === previewClass) {
            // For previewed classes, add directly without removing preview first
            addDirectly(className);
            setPreviewClass(null);
        } else {
            // For regular classes, use standard approach
            removePreview();
            addClass(className);
        }
        
        // Always clear input
        setInputValue('');
        setShowSuggestions(false);
    }, [previewClass, removePreview, addClass, addDirectly]);
    
    // Input change handler
    const handleInputChange = useCallback((value) => {
        setInputValue(value);
        setShowSuggestions(!!value);
        setActiveSuggestion(-1);
        removePreview();
    }, [removePreview]);
    
    // Suggestion mouse handlers
    const handleSuggestionMouseEnter = useCallback((suggestion, index) => {
        lastActionRef.current = 'mouse';
        setActiveSuggestion(index);
        removePreview();
        applyPreview(suggestion);
    }, [removePreview, applyPreview]);
    
    const handleSuggestionMouseLeave = useCallback(() => {
        if (lastActionRef.current === 'mouse') {
            removePreview();
        }
    }, [removePreview]);
    
    const handleSuggestionClick = useCallback((suggestion) => {
        selectClass(suggestion);
    }, [selectClass]);
    
    // Keyboard handler
    const handleKeyDown = useCallback((e) => {
        const filteredSuggestions = availableClasses.filter(
            cls => cls.toLowerCase().includes(inputValue.toLowerCase())
        );
        
        if (!filteredSuggestions.length) return;
        
        switch (e.key) {
            case 'Enter':
                e.preventDefault();
                if (suggestions && activeSuggestion >= 0 && 
                    filteredSuggestions[activeSuggestion]) {
                    selectClass(filteredSuggestions[activeSuggestion]);
                } else if (inputValue) {
                    addClass(inputValue);
                }
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                lastActionRef.current = 'keyboard';
                
                const prevIndex = activeSuggestion < 0 ? 
                    filteredSuggestions.length - 1 : 
                    (activeSuggestion > 0 ? activeSuggestion - 1 : filteredSuggestions.length - 1);
                
                setActiveSuggestion(prevIndex);
                removePreview();
                
                clearPreviewTimeouts();
                previewTimeoutRef.current = setTimeout(() => {
                    applyPreview(filteredSuggestions[prevIndex]);
                }, 50);
                break;
                
            case 'ArrowDown':
                e.preventDefault();
                lastActionRef.current = 'keyboard';
                
                if (!suggestions) setShowSuggestions(true);
                
                const nextIndex = activeSuggestion < 0 ? 
                    0 : 
                    (activeSuggestion < filteredSuggestions.length - 1 ? activeSuggestion + 1 : 0);
                
                setActiveSuggestion(nextIndex);
                removePreview();
                
                clearPreviewTimeouts();
                previewTimeoutRef.current = setTimeout(() => {
                    applyPreview(filteredSuggestions[nextIndex]);
                }, 50);
                break;
                
            case 'Escape':
                setShowSuggestions(false);
                removePreview();
                break;
                
            case 'Tab':
                if (suggestions && activeSuggestion >= 0 && 
                    filteredSuggestions[activeSuggestion]) {
                    e.preventDefault();
                    selectClass(filteredSuggestions[activeSuggestion]);
                }
                break;
        }
    }, [
        activeSuggestion,
        availableClasses,
        inputValue,
        suggestions,
        removePreview,
        clearPreviewTimeouts,
        applyPreview,
        addClass,
        selectClass
    ]);
    
    // Drag and drop handler
    const handleDragEnd = useCallback((result) => {
        if (!result.destination) return;
        
        const items = Array.from(classes);
        const [reorderedItem] = items.splice(result.source.index, 1);
        items.splice(result.destination.index, 0, reorderedItem);
        
        setClassesList(items);
        setClasses(items);
    }, [classes, setClasses]);

    // ========== EFFECTS ==========
    
    // Initialize preservation
    useEffect(() => {
        initializePreviewPreservation();
    }, []);
    
    // Sync with block attributes
    useEffect(() => {
        setClassesList(getClasses());
    }, [className, getClasses]);
    
    // Clean up on unmount
    useEffect(() => {
        return () => {
            if (previewClass) {
                removePreviewFromBlock(clientId, previewClass);
            }
        };
    }, [clientId, previewClass]);
    
    // Handle block selection changes
    useEffect(() => {
        const handleSelectionChange = () => {
            const { getSelectedBlockClientId } = select('core/block-editor');
            const selectedBlockId = getSelectedBlockClientId();
            
            if (selectedBlockId !== clientId && previewClass) {
                removePreview();
            }
        };
        
        try {
            const unsubscribe = select('core/block-editor')?.subscribe?.(handleSelectionChange);
            return () => {
                if (typeof unsubscribe === 'function') {
                    unsubscribe();
                }
            };
        } catch (e) {
            console.error('Error subscribing to selection changes:', e);
        }
    }, [clientId, previewClass, removePreview]);

    // ========== RENDER ==========
    
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
                                onClick={() => handleSuggestionClick(suggestion)}
                                onMouseEnter={() => handleSuggestionMouseEnter(suggestion, index)}
                                onMouseLeave={handleSuggestionMouseLeave}
                                ref={index === activeSuggestion && activeSuggestion >= 0 ? (el) => { 
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

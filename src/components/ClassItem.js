/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';

/**
 * Class Item Component that combines delete action with drag and drop
 */
const ClassItem = ({ className, onRemove, provided, isDragging }) => {
    // Reference to track click vs drag
    const clickTimer = useRef(null);
    const [isClicked, setIsClicked] = useState(false);
    
    // Clean up timer on unmount
    useEffect(() => {
        return () => {
            if (clickTimer.current) clearTimeout(clickTimer.current);
        };
    }, []);
    
    // Handle mouse down - start potential click
    const handleMouseDown = () => {
        // Set clicked state
        setIsClicked(true);
        
        // Set a timer to detect if this is a click vs drag
        clickTimer.current = setTimeout(() => {
            setIsClicked(false);
        }, 200); // Reset after 200ms - if drag starts, we'll still have isClicked true
    };
    
    // Handle click - only trigger if mouse down and up on same element
    const handleClick = (e) => {
        // Only proceed if we tracked a mouse down first
        if (isClicked) {
            // Clear any pending timer
            if (clickTimer.current) {
                clearTimeout(clickTimer.current);
                clickTimer.current = null;
            }
            
            // Make sure we don't have an active drag
            if (!isDragging) {
                // It's a simple click, remove the class
                onRemove(className);
            }
            
            // Reset click state
            setIsClicked(false);
        }
    };

    return (
        <div
            className={`lza-class-button-wrapper ${isDragging ? 'is-dragging' : ''}`}
            ref={provided.innerRef}
            {...provided.draggableProps}
            {...provided.dragHandleProps}
            onMouseDown={handleMouseDown}
            onClick={handleClick}
            aria-label={__('Class item: Click to remove, drag to reorder', 'lza-class-manager')}
        >
            <div className="class-button">
                {className}
            </div>
        </div>
    );
};

export default ClassItem;

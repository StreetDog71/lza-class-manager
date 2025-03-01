import { useState, useRef } from '@wordpress/element';

/**
 * Class list component with native drag and drop functionality
 */
const ClassList = ({ classes, onRemove, onReorder }) => {
    // Store the dragging item reference
    const [draggedClass, setDraggedClass] = useState(null);
    const draggedItemRef = useRef(null);
    
    /**
     * Handle drag start event
     */
    const handleDragStart = (e, className, index) => {
        // Store reference to dragged item
        draggedItemRef.current = index;
        setDraggedClass(className);
        
        // Set data transfer properties
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', className);
        
        // Add dragging class for styling
        e.currentTarget.classList.add('is-dragging');
        
        // This helps with dragging in Firefox
        setTimeout(() => {
            e.currentTarget.classList.add('dragging-active');
        }, 0);
    };
    
    /**
     * Handle drag end event
     */
    const handleDragEnd = (e) => {
        // Reset drag state
        draggedItemRef.current = null;
        setDraggedClass(null);
        
        // Remove dragging classes
        e.currentTarget.classList.remove('is-dragging', 'dragging-active');
    };
    
    /**
     * Handle drag over event to enable dropping
     */
    const handleDragOver = (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        return false;
    };
    
    /**
     * Handle drop event
     */
    const handleDrop = (e, targetIndex) => {
        e.preventDefault();
        
        // Exit if no item is being dragged or it's dropped onto itself
        const sourceIndex = draggedItemRef.current;
        if (sourceIndex === null || sourceIndex === targetIndex) {
            return;
        }
        
        // Create reordered array
        const newClasses = [...classes];
        const [draggedItem] = newClasses.splice(sourceIndex, 1);
        newClasses.splice(targetIndex, 0, draggedItem);
        
        // Update classes order
        onReorder(newClasses);
        
        return false;
    };
    
    /**
     * Handle drag enter event for visual feedback
     */
    const handleDragEnter = (e) => {
        e.currentTarget.classList.add('drag-over');
    };
    
    /**
     * Handle drag leave event for visual feedback
     */
    const handleDragLeave = (e) => {
        e.currentTarget.classList.remove('drag-over');
    };

    return (
        <div className="lza-class-list">
            {classes.map((className, index) => (
                <div
                    key={className}
                    className="lza-class-button-wrapper"
                    draggable="true"
                    onDragStart={(e) => handleDragStart(e, className, index)}
                    onDragEnd={handleDragEnd}
                    onDragOver={handleDragOver}
                    onDrop={(e) => handleDrop(e, index)}
                    onDragEnter={handleDragEnter}
                    onDragLeave={handleDragLeave}
                >
                    <button
                        type="button"
                        className="class-button"
                        onClick={() => onRemove(className)}
                        title="Click to remove, drag to reorder"
                    >
                        {className}
                    </button>
                </div>
            ))}
        </div>
    );
};

export default ClassList;

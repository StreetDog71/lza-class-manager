/**
 * WordPress dependencies
 */
import { select, dispatch } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';

// Simple log helper - can be disabled in production
const log = process.env.NODE_ENV === 'development' ? 
    (message, ...args) => console.log(`[block-preview] ${message}`, ...args) : 
    () => {};

/**
 * Apply a preview class to a block using WordPress's data store
 * 
 * This approach doesn't try to manipulate the DOM directly,
 * but rather uses WordPress's own APIs to update the block attributes
 * 
 * @param {string} clientId Block client ID
 * @param {string} previewClass Class to preview
 * @param {boolean} removeOtherPreviews Whether to remove other preview classes
 */
export const applyPreviewToBlock = (clientId, previewClass, removeOtherPreviews = true) => {
    // Exit early if no valid inputs
    if (!clientId || !previewClass) return false;

    try {
        log(`Applying preview class ${previewClass} to block ${clientId}`);

        // Get block from store
        const blockEditor = select('core/block-editor');
        const block = blockEditor.getBlock(clientId);
        
        if (!block) {
            log(`Block ${clientId} not found in editor`);
            return false;
        }
        
        // Get current attributes and class names
        const currentAttributes = { ...block.attributes };
        let currentClasses = currentAttributes.className ? 
            currentAttributes.className.split(' ').filter(Boolean) : [];
            
        // Track preview classes for removal if needed
        if (removeOtherPreviews && window._lzaPreviewClasses) {
            // Remove existing preview classes
            currentClasses = currentClasses.filter(cls => 
                !window._lzaPreviewClasses.includes(cls));
        }

        // Check if class already exists
        if (currentClasses.includes(previewClass)) {
            log(`Class ${previewClass} already exists on block`);
            return true; // Class already exists, nothing to do
        }
        
        // Add the new preview class
        const newClasses = [...currentClasses, previewClass];
        const newClassName = newClasses.join(' ');
        
        // Update the block attributes
        dispatch('core/block-editor').updateBlockAttributes(
            clientId,
            { className: newClassName }
        );
        
        // Track this class as a preview
        if (!window._lzaPreviewClasses) window._lzaPreviewClasses = [];
        if (!window._lzaPreviewClasses.includes(previewClass)) {
            window._lzaPreviewClasses.push(previewClass);
        }
        
        log(`Applied preview class. Block now has: ${newClassName}`);
        return true;
    } catch (err) {
        log('Error applying preview class:', err);
        return false;
    }
};

/**
 * Improved coordination between preview removal and class addition
 */

// Add a tracking object for classes being permanently added
window._lzaClassesBeingAdded = {};

/**
 * Enhanced removePreviewFromBlock to prevent removing classes being added
 */
export const removePreviewFromBlock = (clientId, previewClass = null) => {
    // Exit early if no valid inputs
    if (!clientId) return false;

    try {
        // Add extra check at the very top for classes being added
        if (previewClass && window._lzaClassesBeingAdded && window._lzaClassesBeingAdded[clientId] === previewClass) {
            log(`PROTECTED: Blocking removal of "${previewClass}" as it's being permanently added to block ${clientId}`);
            return true; // Pretend we succeeded but do nothing
        }

        log(`Removing ${previewClass || 'all'} preview classes from block ${clientId}`);

        // Skip specific cases where we know we're adding a class permanently
        if (window._lzaClassesBeingAdded[clientId] === previewClass) {
            log(`Skipping removal of "${previewClass}" as it's being actively added to block ${clientId}`);
            return true;
        }

        // Check if this class is marked as permanent
        if (previewClass && 
            window._lzaPermanentClasses[clientId] && 
            window._lzaPermanentClasses[clientId].includes(previewClass)) {
            log(`Skipping removal of "${previewClass}" as it's marked as permanent for block ${clientId}`);
            return true;
        }

        // Get block from store
        const blockEditor = select('core/block-editor');
        const block = blockEditor.getBlock(clientId);
        
        if (!block) {
            log(`Block ${clientId} not found in editor`);
            return false;
        }
        
        // Get current attributes and class names
        const currentAttributes = { ...block.attributes };
        let currentClasses = currentAttributes.className ? 
            currentAttributes.className.split(' ').filter(Boolean) : [];
            
        if (!currentClasses.length) return true; // No classes to remove
        
        // Keep track of the original class list for logging
        const originalClasses = [...currentClasses];
        let newClasses;
        
        if (previewClass) {
            // Remove specific preview class
            newClasses = currentClasses.filter(cls => cls !== previewClass);
            
            // Update tracking
            if (window._lzaPreviewClasses) {
                window._lzaPreviewClasses = window._lzaPreviewClasses.filter(
                    cls => cls !== previewClass
                );
            }
        } else if (window._lzaPreviewClasses) {
            // Remove all tracked preview classes
            newClasses = currentClasses.filter(cls => 
                !window._lzaPreviewClasses.includes(cls));
            window._lzaPreviewClasses = [];
        } else {
            // No tracking info, nothing to remove
            return true;
        }
        
        // Only update if classes have changed
        if (newClasses.length !== currentClasses.length) {
            const newClassName = newClasses.join(' ');
            
            // Update the block attributes
            dispatch('core/block-editor').updateBlockAttributes(
                clientId,
                { className: newClassName || '' }
            );
            
            if (previewClass) {
                log(`Removed preview class "${previewClass}". Block now has: ${newClassName}`);
                log(`Classes before: [${originalClasses.join(', ')}]`);
                log(`Classes after: [${newClasses.join(', ')}]`);
                
                // Extra check to make sure the class was actually removed
                if (newClasses.includes(previewClass)) {
                    log(`WARNING: Failed to remove preview class "${previewClass}" - still present in classes!`);
                }
            }
        }
        
        return true;
    } catch (err) {
        log('Error removing preview class:', err);
        return false;
    }
};

/**
 * Function to mark a class as being permanently added - to prevent removal operations
 * 
 * @param {string} clientId Block client ID
 * @param {string} className Class being added
 * @param {boolean} isAdding True when adding, false when done
 */
export const markClassBeingAdded = (clientId, className, isAdding = true) => {
    if (!clientId) return;
    
    if (isAdding) {
        window._lzaClassesBeingAdded[clientId] = className;
        log(`Marked "${className}" as being added to block ${clientId}`);
    } else if (window._lzaClassesBeingAdded[clientId] === className) {
        delete window._lzaClassesBeingAdded[clientId];
        log(`Unmarked "${className}" from being added to block ${clientId}`);
    }
};

/**
 * Register hooks to preserve preview classes during block selection
 */
export const initializePreviewPreservation = () => {
    if (window._lzaPreviewPreservationInitialized) return;
    
    try {
        // Check if WordPress hooks are available
        if (typeof wp === 'undefined' || !wp.hooks || !wp.hooks.addFilter) {
            log('WordPress hooks API not available');
            return;
        }
        
        // Use WordPress filter system instead of direct override
        addFilter(
            'blocks.getBlockAttributes',
            'lza-class-manager/preserve-preview-classes',
            (attributes, blockType) => {
                // Only modify attributes if we have preview classes to preserve
                if (window._lzaPreviewClasses?.length > 0 && attributes) {
                    // Check if we have existing classes
                    let currentClasses = attributes.className ? 
                        attributes.className.split(' ').filter(Boolean) : [];
                        
                    // Add any missing preview classes
                    let needsUpdate = false;
                    for (const previewClass of window._lzaPreviewClasses) {
                        if (!currentClasses.includes(previewClass)) {
                            currentClasses.push(previewClass);
                            needsUpdate = true;
                        }
                    }
                    
                    // Update the className if needed
                    if (needsUpdate) {
                        attributes = {
                            ...attributes,
                            className: currentClasses.join(' ')
                        };
                    }
                }
                
                return attributes;
            }
        );
        
        log('Preview preservation initialized using WordPress filters');
        window._lzaPreviewPreservationInitialized = true;
    } catch (err) {
        log('Error initializing preview preservation:', err);
    }
};

/**
 * Track classes that should never be removed as previews
 */
window._lzaPermanentClasses = {};

/**
 * Mark a class as permanent for a block, preventing it from being treated as a preview
 * 
 * @param {string} clientId Block client ID
 * @param {string} className The class to mark as permanent
 */
export const markClassAsPermanent = (clientId, className) => {
    if (!clientId || !className) return;
    
    if (!window._lzaPermanentClasses[clientId]) {
        window._lzaPermanentClasses[clientId] = [];
    }
    
    if (!window._lzaPermanentClasses[clientId].includes(className)) {
        window._lzaPermanentClasses[clientId].push(className);
        log(`Marked class "${className}" as permanent for block ${clientId}`);
    }
};

/**
 * Set up stronger protection for a block's classes
 */
export const protectBlockClasses = (clientId) => {
    if (!clientId) return () => {};
    
    try {
        // Create a monitor for the block's attributes
        const monitor = select('core/block-editor').subscribe(() => {
            const block = select('core/block-editor').getBlock(clientId);
            if (!block) return;
            
            // Check if any of our protected classes were removed
            const currentClasses = block.attributes.className ? 
                block.attributes.className.split(' ').filter(Boolean) : [];
                
            // If we have permanent classes for this block
            const permanentClasses = window._lzaPermanentClasses[clientId] || [];
            if (permanentClasses.length > 0) {
                // Find any permanent classes that are missing
                const missingClasses = permanentClasses.filter(
                    cls => !currentClasses.includes(cls)
                );
                
                // If any are missing, add them back
                if (missingClasses.length > 0) {
                    log(`Re-adding permanent classes: ${missingClasses.join(', ')}`);
                    
                    const updatedClasses = [...currentClasses, ...missingClasses];
                    dispatch('core/block-editor').updateBlockAttributes(
                        clientId,
                        { className: updatedClasses.join(' ') }
                    );
                }
            }
        });
        
        return monitor;
    } catch (e) {
        log('Error setting up block class protection:', e);
        return () => {};
    }
};

/**
 * Create a function to force add a class to the block
 * This is used as a last resort when normal methods fail
 * 
 * @param {string} clientId Block client ID  
 * @param {string} className Class name to add
 * @return {boolean} Success status
 */
export const forceAddClassToBlock = (clientId, className) => {
    if (!clientId || !className) return false;
    
    try {
        log(`Force adding class ${className} to block ${clientId}`);
        
        // 1. First, mark this class as being added so it can't be removed
        window._lzaClassesBeingAdded = window._lzaClassesBeingAdded || {};
        window._lzaClassesBeingAdded[clientId] = className;
        
        // 2. Get current classes from the store
        const block = select('core/block-editor').getBlock(clientId);
        if (!block) {
            log(`Block ${clientId} not found`);
            return false;
        }
        
        // 3. Add the class if it's not already there
        const currentClasses = block.attributes.className ? 
            block.attributes.className.split(' ').filter(Boolean) : [];
            
        if (currentClasses.includes(className)) {
            log(`Class ${className} already exists on block - ensuring it stays`);
        } else {
            const newClasses = [...currentClasses, className];
            dispatch('core/block-editor').updateBlockAttributes(
                clientId,
                { className: newClasses.join(' ') }
            );
            log(`Force added class ${className} to block`);
        }
        
        // 4. Keep protection for a short while, then clean up
        setTimeout(() => {
            if (window._lzaClassesBeingAdded && 
                window._lzaClassesBeingAdded[clientId] === className) {
                delete window._lzaClassesBeingAdded[clientId];
            }
        }, 500);
        
        return true;
    } catch (err) {
        log('Error force adding class:', err);
        return false;
    }
};

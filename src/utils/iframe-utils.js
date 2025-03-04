/**
 * Utilities for working with the WordPress editor iframe
 */

// Simple log helpers - can be disabled in production
const debug = process.env.NODE_ENV === 'development' ? 
    (message, ...args) => console.log(`[iframe-utils] ${message}`, ...args) : 
    () => {};

const log = debug;

/**
 * Get all iframes in the editor
 * This helps ensure we find the right one
 */
const getAllEditorIframes = () => {
    const iframes = [];
    
    // Modern editor (WordPress 5.8+)
    const editorCanvas = document.querySelector('iframe[name="editor-canvas"]');
    if (editorCanvas) {
        iframes.push(editorCanvas);
    }
    
    // Legacy iframe approach (pre-5.8)
    const legacyEditorFrame = document.querySelector('.edit-post-visual-editor iframe');
    if (legacyEditorFrame) {
        iframes.push(legacyEditorFrame);
    }
    
    // FSE editor iframe
    const fseEditorFrame = document.querySelector('.editor-canvas-container iframe');
    if (fseEditorFrame) {
        iframes.push(fseEditorFrame);
    }
    
    // Other potential iframes
    const otherIframes = document.querySelectorAll('.edit-site-visual-editor iframe');
    if (otherIframes.length) {
        otherIframes.forEach(iframe => iframes.push(iframe));
    }
    
    return iframes;
};

/**
 * Get the editor iframe content document
 * 
 * @return {Document|null} The iframe's document or null if not found
 */
export const getEditorIframeDocument = () => {
    const iframes = getAllEditorIframes();
    
    for (let i = 0; i < iframes.length; i++) {
        try {
            if (iframes[i].contentDocument) {
                debug('Found usable iframe document');
                return iframes[i].contentDocument;
            }
        } catch (e) {
            debug('Error accessing iframe contentDocument:', e);
        }
    }
    
    debug('No iframe document found');
    return null;
};

/**
 * Get a block element from the editor by its client ID
 * 
 * @param {string} clientId The block's client ID
 * @return {HTMLElement|null} The block element or null if not found
 */
export const getBlockElementByClientId = (clientId) => {
    if (!clientId) {
        debug('No clientId provided to getBlockElementByClientId');
        return null;
    }
    
    try {
        // Try in all potential iframe locations
        const iframes = getAllEditorIframes();
        let blockElement = null;
        
        // Check each iframe first
        for (let i = 0; i < iframes.length; i++) {
            try {
                if (iframes[i].contentDocument) {
                    const foundElement = iframes[i].contentDocument.querySelector(`[data-block="${clientId}"]`);
                    if (foundElement) {
                        debug(`Found block in iframe #${i}`);
                        blockElement = foundElement;
                        break;
                    }
                }
            } catch (e) {
                debug(`Error checking iframe #${i}:`, e);
            }
        }
        
        // If not found in iframes, try in main document (may happen in certain WP versions)
        if (!blockElement) {
            blockElement = document.querySelector(`[data-block="${clientId}"]`);
            if (blockElement) {
                debug('Found block in main document');
            }
        }
        
        // Final check for block presence
        if (!blockElement) {
            debug(`Block with ID ${clientId} not found in any document`);
            
            // Try alternate selector formats that might be used in different WP versions
            const alternateSelectors = [
                `[id="block-${clientId}"]`,
                `[data-block-id="${clientId}"]`
            ];
            
            for (const selector of alternateSelectors) {
                // Check iframes first
                for (let i = 0; i < iframes.length; i++) {
                    try {
                        if (iframes[i].contentDocument) {
                            const element = iframes[i].contentDocument.querySelector(selector);
                            if (element) {
                                debug(`Found block using alternate selector ${selector} in iframe`);
                                return element;
                            }
                        }
                    } catch (e) {
                        // Ignore errors and continue trying
                    }
                }
                
                // Then try main document
                const element = document.querySelector(selector);
                if (element) {
                    debug(`Found block using alternate selector ${selector} in main document`);
                    return element;
                }
            }
        }
        
        return blockElement;
    } catch (e) {
        debug('Error in getBlockElementByClientId:', e);
        return null;
    }
};

/**
 * Clear all preview classes from a block based on a prefix
 * This ensures we don't have multiple preview classes applied
 * 
 * @param {string} clientId The block's client ID
 * @param {string[]} previewClassPrefixes Array of class prefixes to clear (e.g., ['bg-', 'text-'])
 */
export const clearPreviewClassesByPrefix = (clientId, previewClassPrefixes = ['bg-', 'text-', 'p-', 'm-']) => {
    const blockElement = getBlockElementByClientId(clientId);
    
    if (!blockElement || !blockElement.classList) {
        debug(`Cannot clear preview classes: Block element not found or has no classList for ID ${clientId}`);
        return;
    }
    
    // Get all classes on the element
    const currentClasses = Array.from(blockElement.classList);
    debug(`Current classes before clearing: ${currentClasses.join(', ')}`);
    
    // Find classes that match our prefixes
    const classesToRemove = currentClasses.filter(cls => {
        return previewClassPrefixes.some(prefix => cls.startsWith(prefix));
    });
    
    // Remove matching classes that might be previews
    classesToRemove.forEach(cls => {
        try {
            blockElement.classList.remove(cls);
            debug(`Removed class ${cls} from block ${clientId}`);
        } catch (e) {
            debug(`Error removing class ${cls}:`, e);
        }
    });
    
    debug(`Classes after clearing: ${Array.from(blockElement.classList).join(', ')}`);
};

/**
 * Safely modify a block's classes with retry mechanism
 * 
 * @param {string} clientId Block client ID
 * @param {Function} modifier Function that modifies the classList
 * @param {number} maxRetries Maximum number of retry attempts
 * @return {boolean} True if successful, false otherwise
 */
const safelyModifyBlockClasses = (clientId, modifier, maxRetries = 3) => {
    let retries = 0;
    
    const attempt = () => {
        try {
            const blockElement = getBlockElementByClientId(clientId);
            if (!blockElement || !blockElement.classList) {
                log(`No valid block element found for ID ${clientId}`);
                return false;
            }
            
            // Apply the modifier function to the classList
            const result = modifier(blockElement.classList);
            
            // Force a style recalculation to ensure visual updates
            void blockElement.offsetWidth;
            
            return result;
        } catch (e) {
            retries++;
            if (retries <= maxRetries) {
                log(`Retrying class modification (${retries}/${maxRetries})`, e);
                return attempt();
            } else {
                log('All retries failed when modifying block classes', e);
                return false;
            }
        }
    };
    
    return attempt();
};

/**
 * Add a class to a block in the editor with retry mechanism
 * 
 * @param {string} clientId The block's client ID
 * @param {string} className The class to add
 * @param {boolean} clearPreviews Whether to clear existing preview classes with similar prefixes
 * @return {boolean} True if successful, false otherwise
 */
export const addClassToBlock = (clientId, className, clearPreviews = true) => {
    if (!className) {
        log('No className provided to addClassToBlock');
        return false;
    }
    
    // First clear existing preview classes if needed
    if (clearPreviews && className.includes('-')) {
        const prefix = className.split('-')[0] + '-';
        clearPreviewClassesByPrefix(clientId, [prefix]);
    }
    
    // Now add the new class
    return safelyModifyBlockClasses(clientId, (classList) => {
        if (!classList.contains(className)) {
            classList.add(className);
            log(`Added class ${className} to block ${clientId}`);
            log(`Block now has classes: ${classList}`);
            return true;
        }
        return false;
    });
};

/**
 * Remove a class from a block in the editor with retry mechanism
 * 
 * @param {string} clientId The block's client ID
 * @param {string} className The class to remove
 * @return {boolean} True if successful, false otherwise
 */
export const removeClassFromBlock = (clientId, className) => {
    if (!className) {
        log('No className provided to removeClassFromBlock');
        return false;
    }
    
    return safelyModifyBlockClasses(clientId, (classList) => {
        if (classList.contains(className)) {
            classList.remove(className);
            log(`Removed class ${className} from block ${clientId}`);
            log(`Block now has classes: ${classList}`);
            return true;
        }
        return false;
    });
};

/**
 * Fix for WordPress class handling - preserve applied classes on selection
 * 
 * This preserves classes when WordPress tries to re-render the block
 * 
 * @param {string} clientId The block client ID
 */
export const preserveBlockClasses = (clientId) => {
    if (!clientId) return;

    try {
        // Backup original wp.blocks.getBlockAttributes
        if (!window._originalGetBlockAttributes && window.wp && window.wp.blocks) {
            window._originalGetBlockAttributes = window.wp.blocks.getBlockAttributes;
            
            // Override the function to preserve our preview classes
            window.wp.blocks.getBlockAttributes = (blockType, attributes) => {
                const originalAttrs = window._originalGetBlockAttributes(blockType, attributes);
                
                // If this is our target block and we have a preview class to preserve
                if (attributes?.clientId === clientId && window.lzaClassPreview) {
                    // Make sure the class is included in the attributes
                    if (originalAttrs.className) {
                        const classes = originalAttrs.className.split(' ');
                        if (!classes.includes(window.lzaClassPreview)) {
                            classes.push(window.lzaClassPreview);
                            originalAttrs.className = classes.join(' ');
                        }
                    } else {
                        originalAttrs.className = window.lzaClassPreview;
                    }
                }
                
                return originalAttrs;
            };
            
            log('Installed getBlockAttributes override');
        }
    } catch (e) {
        log('Error installing attribute override', e);
    }
};

/**
 * Store the current preview class globally for WordPress hooks to use
 * 
 * @param {string|null} className Class name to store or null to clear 
 */
export const setGlobalPreviewClass = (className) => {
    window.lzaClassPreview = className;
    log('Set global preview class:', className);
};

/**
 * Check if a block has a specific class
 * 
 * @param {string} clientId The block's client ID
 * @param {string} className The class to check for
 * @return {boolean} True if the block has the class, false otherwise
 */
export const blockHasClass = (clientId, className) => {
    const blockElement = getBlockElementByClientId(clientId);
    
    if (!blockElement || !className) {
        return false;
    }
    
    try {
        return blockElement.classList.contains(className);
    } catch (e) {
        debug(`Error in blockHasClass:`, e);
        return false;
    }
};

/**
 * Debug function to list all classes on a block
 * 
 * @param {string} clientId The block's client ID
 * @return {string[]} Array of classes on the block
 */
export const getBlockClasses = (clientId) => {
    const blockElement = getBlockElementByClientId(clientId);
    
    if (!blockElement) {
        debug(`No block element found for ID ${clientId}`);
        return [];
    }
    
    try {
        return Array.from(blockElement.classList);
    } catch (e) {
        debug(`Error getting block classes:`, e);
        return [];
    }
};

/**
 * Simple debounce function to limit frequent calls
 * 
 * @param {Function} func The function to debounce
 * @param {number} wait Wait time in milliseconds
 * @return {Function} Debounced function
 */
export const debounce = (func, wait = 50) => {
    let timeout;
    return (...args) => {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

/**
 * Track active preview operations to prevent collisions
 */
let activePreviewOperation = null;
let previewQueue = [];

/**
 * Schedule a preview operation that won't collide with others
 * 
 * @param {Function} operation Function to execute
 * @param {string} id Identifier for this operation
 */
export const schedulePreviewOperation = (operation, id) => {
    // Cancel any existing operation with the same ID
    previewQueue = previewQueue.filter(item => item.id !== id);
    
    // Add this operation to the queue
    previewQueue.push({ operation, id, timestamp: Date.now() });
    
    // Process the queue
    processPreviewQueue();
};

/**
 * Process the preview operation queue
 */
const processPreviewQueue = debounce(() => {
    // Skip if there's an active operation or empty queue
    if (activePreviewOperation || previewQueue.length === 0) return;
    
    // Sort queue by timestamp (oldest first)
    previewQueue.sort((a, b) => a.timestamp - b.timestamp);
    
    // Get the next operation
    const next = previewQueue.shift();
    activePreviewOperation = next.id;
    
    // Execute the operation
    try {
        next.operation(() => {
            // Operation complete callback
            activePreviewOperation = null;
            // Check if more operations are queued
            if (previewQueue.length > 0) {
                processPreviewQueue();
            }
        });
    } catch (e) {
        debug(`Error executing preview operation ${next.id}:`, e);
        activePreviewOperation = null;
        // Continue processing despite error
        if (previewQueue.length > 0) {
            processPreviewQueue();
        }
    }
}, 10);

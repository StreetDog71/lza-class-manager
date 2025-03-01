/**
 * WordPress dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { InspectorControls } from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import ClassManagerPanel from './components/ClassManagerPanel';

/**
 * Add the Class Manager panel to the block inspector controls
 */
const withClassManagerPanel = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        return (
            <>
                <BlockEdit {...props} />
                <InspectorControls>
                    <ClassManagerPanel clientId={props.clientId} />
                </InspectorControls>
            </>
        );
    };
}, 'withClassManagerPanel');

// Add our filter to the WordPress block editor
addFilter(
    'editor.BlockEdit',
    'lza-class-manager/with-class-manager-panel',
    withClassManagerPanel
);

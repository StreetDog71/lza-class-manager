/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { Fragment } from '@wordpress/element';

/**
 * Internal dependencies
 */
import ClassManagerPanel from './components/ClassManagerPanel';

/**
 * Add custom class manager to all blocks using HOC
 */
const withClassManager = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        
        return (
            <Fragment>
                <BlockEdit {...props} />
                <InspectorControls>
                    <PanelBody
                        title={__('Class Manager', 'lza-class-manager')}
                        initialOpen={true}
                    >
                        <ClassManagerPanel blockProps={props} />
                    </PanelBody>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'withClassManager');

// Add our filter to editor.BlockEdit
addFilter(
    'editor.BlockEdit',
    'lza-class-manager/with-class-manager',
    withClassManager
);

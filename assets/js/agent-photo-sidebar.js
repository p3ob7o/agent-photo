( function( wp ) {
	const { registerPlugin } = wp.plugins;
	const { createElement } = wp.element;
	const { __ } = wp.i18n;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, Button, Spinner, ButtonGroup } = wp.components;
	const { addFilter } = wp.hooks;
	const { useState } = wp.element;

	// Add custom controls to image block
	function addAgentPhotoControls( BlockEdit ) {
		return ( props ) => {
			// Add states for modal and button
			const [showTitleModal, setShowTitleModal] = useState(false);
			const [titleOptions, setTitleOptions] = useState([]);
			const [isProcessing, setIsProcessing] = useState(false);
			const [hasProcessedImage, setHasProcessedImage] = useState(false);

			// Only add to image block
			if ( props.name !== 'core/image' ) {
				return createElement( BlockEdit, props );
			}

			const handleTitleSelect = (selectedTitle) => {
				// Get the editor's data store
				const { editPost } = wp.data.dispatch('core/editor');
				const { getCurrentPost } = wp.data.select('core/editor');
				
				// Get current post data
				const currentPost = getCurrentPost();
				
				// Update the post title
				editPost({ 
					title: selectedTitle,
					id: currentPost.id 
				}).then(() => {
					// Force a save
					wp.data.dispatch('core/editor').savePost();
				});
				
				setShowTitleModal(false);
				// Don't reset hasProcessedImage so we can review titles again
			};

			const handleReviewTitles = () => {
				setShowTitleModal(true);
			};

			const handleAgentPhotoClick = async () => {
				if (hasProcessedImage) {
					handleReviewTitles();
					return;
				}

				setIsProcessing(true);
				console.log('Agent Photo button clicked.');

				// Get the current image data
				const imageUrl = props.attributes.url;
				const imageId = props.attributes.id;

				if (!imageUrl || !imageId) {
					console.error('Image data is missing.');
					return;
				}

				// Log the request data
				console.log('Request data:', {
					url: `${agentPhotoSettings.restUrl}agent-photo/v1/process`,
					imageUrl,
					imageId,
					nonce: agentPhotoSettings.nonce
				});

				try {
					// Make the API request to your WordPress endpoint
					const response = await fetch(`${agentPhotoSettings.restUrl}agent-photo/v1/process`, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': agentPhotoSettings.nonce
						},
						body: JSON.stringify({ imageId, imageUrl })
					});

					// Log the raw response
					console.log('Response status:', response.status);
					console.log('Response headers:', response.headers);

					const data = await response.json();
					console.log('Response data:', data);

					// Handle the response
					if (data.success) {
						console.log('Image processed successfully:', data);

						// Update the image alt text
						props.setAttributes({
							alt: data.altText || props.attributes.alt
						});

						// Insert a new paragraph block with the generated legend
						const paragraphBlock = wp.blocks.createBlock('core/paragraph', {
							content: data.legend
						});

						// Get the current block's index
						const { getBlockIndex } = wp.data.select('core/block-editor');
						const { insertBlock, selectBlock } = wp.data.dispatch('core/block-editor');
						const currentBlockIndex = getBlockIndex(props.clientId);
						
						// Insert the block after the current block
						insertBlock(paragraphBlock, currentBlockIndex + 1);

						// Keep focus on the original image block
						selectBlock(props.clientId);

						// Show title modal if we have title options
						if (data.title1 && data.title2 && data.title3) {
							setTitleOptions([data.title1, data.title2, data.title3]);
							setShowTitleModal(true);
							setHasProcessedImage(true);
						}
					} else {
						console.error('Error processing image:', data.message || 'Unknown error');
					}
				} catch (error) {
					console.error('Error:', error);
				} finally {
					setIsProcessing(false);
				}
			};

			const handleProcessAgain = async () => {
				setIsProcessing(true);
				setHasProcessedImage(false);
				
				const imageUrl = props.attributes.url;
				const imageId = props.attributes.id;

				if (!imageUrl || !imageId) {
					console.error('Image data is missing.');
					setIsProcessing(false);
					return;
				}

				try {
					const response = await fetch(`${agentPhotoSettings.restUrl}agent-photo/v1/process`, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': agentPhotoSettings.nonce
						},
						body: JSON.stringify({ imageId, imageUrl })
					});

					const data = await response.json();

					if (data.success) {
						// Update the image alt text
						props.setAttributes({
							alt: data.altText || props.attributes.alt
						});

						// Get block editor utilities
						const { getBlockIndex, getBlocks } = wp.data.select('core/block-editor');
						const { insertBlock, selectBlock, removeBlock } = wp.data.dispatch('core/block-editor');
						const currentBlockIndex = getBlockIndex(props.clientId);

						// Find and remove the previous legend block if it exists
						const blocks = getBlocks();
						const nextBlock = blocks[currentBlockIndex + 1];
						if (nextBlock && nextBlock.name === 'core/paragraph') {
							removeBlock(nextBlock.clientId);
						}

						// Insert the new legend block
						const paragraphBlock = wp.blocks.createBlock('core/paragraph', {
							content: data.legend
						});
						
						// Insert the block after the current block
						insertBlock(paragraphBlock, currentBlockIndex + 1);

						// Keep focus on the original image block
						selectBlock(props.clientId);

						// Store title options and set processed state
						if (data.title1 && data.title2 && data.title3) {
							setTitleOptions([data.title1, data.title2, data.title3]);
							setHasProcessedImage(true);
						}
					} else {
						console.error('Error processing image:', data.message || 'Unknown error');
					}
				} catch (error) {
					console.error('Error:', error);
				} finally {
					setIsProcessing(false);
				}
			};

			return createElement(
				wp.element.Fragment,
				{},
				createElement( BlockEdit, props ),
				createElement(
					InspectorControls,
					{},
					createElement(
						PanelBody,
						{
							title: __( 'Agent Photo', 'agent-photo' ),
							initialOpen: true,
						},
						hasProcessedImage ?
							// Show two buttons when processed
							createElement(
								ButtonGroup,
								{ className: 'agent-photo-button-group' },
								createElement(
									Button,
									{
										isPrimary: true,
										onClick: handleProcessAgain,
										className: 'agent-photo-button',
										disabled: isProcessing,
									},
									isProcessing ? 
										createElement(Spinner) : 
										__('Process Again', 'agent-photo')
								),
								createElement(
									Button,
									{
										isSecondary: true,
										onClick: handleReviewTitles,
										className: 'agent-photo-button',
									},
									__('Review Titles', 'agent-photo')
								)
							)
							:
							// Show single button before processing
							createElement(
								Button,
								{
									isPrimary: true,
									onClick: handleAgentPhotoClick,
									className: 'agent-photo-button',
									disabled: isProcessing,
								},
								isProcessing ? 
									createElement(Spinner) : 
									__('Process Image', 'agent-photo')
							)
					)
				),
				showTitleModal && createElement(
					window.AgentPhotoModal,
					{
						titles: titleOptions,
						onSelect: handleTitleSelect,
						onClose: () => setShowTitleModal(false)
					}
				)
			);
		};
	}

	// Register the filter
	addFilter(
		'editor.BlockEdit',
		'agent-photo/with-inspector-controls',
		addAgentPhotoControls
	);

} )( window.wp ); 
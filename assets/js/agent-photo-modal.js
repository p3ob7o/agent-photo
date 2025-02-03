( function( wp ) {
	const { Modal, Button } = wp.components;
	const { createElement } = wp.element;
	const { __ } = wp.i18n;

	const AgentPhotoModal = ( { titles, onSelect, onClose } ) => {
		return createElement(
			Modal,
			{
				title: __( 'Select a Post Title', 'agent-photo' ),
				onRequestClose: onClose,
				className: 'agent-photo-title-modal'
			},
			createElement(
				'div',
				{ className: 'agent-photo-title-buttons' },
				titles.map( ( title, index ) =>
					createElement(
						Button,
						{
							key: index,
							isPrimary: true,
							onClick: () => onSelect( title ),
							className: 'agent-photo-title-button',
							style: { margin: '5px', display: 'block', width: '100%', textAlign: 'left' }
						},
						title
					)
				)
			)
		);
	};

	// Expose the modal globally for later use.
	window.AgentPhotoModal = AgentPhotoModal;
} )( window.wp ); 
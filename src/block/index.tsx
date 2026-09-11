import { registerBlockType } from '@wordpress/blocks';
import type { BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import edit from './edit';
import type { IconLibraryAttributes } from './edit';
import { ReactComponent as icon } from './icon.svg';
import './style.css';

// All attributes live in block.json (single source of truth);
// only the opt-in settings subset is passed here.
registerBlockType( metadata.name, {
	icon,
	edit,
	save: () => null,
} as unknown as BlockConfiguration< IconLibraryAttributes > );

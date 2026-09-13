import { useEffect, useMemo, useState } from '@wordpress/element';
import './editor.css';
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	BlockControls,
	useBlockProps,
	LinkControl,
	useSetting,
} from '@wordpress/block-editor';
import { useDispatch } from '@wordpress/data';
import {
	PanelBody,
	Modal,
	ToolbarGroup,
	ToolbarButton,
	Popover,
	SelectControl,
	TextControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { CSSProperties, SVGProps } from 'react';
import {
	resolveColorValue,
	resolveDimensionValue,
	resolveSpacingValue,
} from './attribute-utils';
import { toDimensionPresetEntries } from './icon-sizes';
import { parseSymbols, type SpriteSymbol } from './icon-colors';

export type IconLibraryAttributes = {
	symbolId: string;
	url: string;
	opensInNewTab: boolean;
	rel: string;
	label: string;
	width: string;
	height: string;
	style?: {
		color?: {
			text?: string;
			background?: string;
		};
		dimensions?: {
			width?: string;
		};
		spacing?: {
			padding?: Partial<
				Record< 'top' | 'right' | 'bottom' | 'left', string >
			>;
			margin?: Partial<
				Record< 'top' | 'right' | 'bottom' | 'left', string >
			>;
		};
	};
	textColor?: string;
	backgroundColor?: string;
};

interface SymbolOption {
	label: string;
	value: string;
}

const SPRITE_URL =
	window.iconLibrarySettings?.spriteUrl ||
	'/wp-content/plugins/icon-library/sprite.svg';

export default function Edit( {
	attributes,
	setAttributes,
	clientId,
}: BlockEditProps< IconLibraryAttributes > ) {
	const {
		symbolId,
		url,
		opensInNewTab,
		rel,
		label,
		width,
		height,
		style,
		textColor,
		backgroundColor,
	} = attributes;
	const [ symbols, setSymbols ] = useState< SpriteSymbol[] >( [] );
	const [ error, setError ] = useState( '' );
	const [ isLinkPickerOpen, setIsLinkPickerOpen ] = useState( false );
	const [ isIconPickerOpen, setIsIconPickerOpen ] = useState( false );
	const [ reload, setReload ] = useState( 0 );

	const dimensionSizes = useSetting( 'dimensions.dimensionSizes' );

	const dimensionPresets = useMemo(
		() => toDimensionPresetEntries( dimensionSizes ),
		[ dimensionSizes ]
	);
	const presetSizes = useMemo( () => {
		const map: Record< string, string > = {};
		for ( const preset of dimensionPresets ) {
			if ( preset.slug ) {
				map[ preset.slug ] = preset.size;
			}
		}
		return map;
	}, [ dimensionPresets ] );

	useEffect( () => {
		let aborted = false;
		( async () => {
			try {
				const res = await fetch( SPRITE_URL, {
					credentials: 'same-origin',
				} );
				if ( ! res.ok ) {
					throw new Error( `${ res.status }` );
				}
				const text = await res.text();
				if ( ! aborted ) {
					const parsed = parseSymbols( text );
					setSymbols( parsed );
					if ( ! symbolId && parsed.length > 0 ) {
						setAttributes( { symbolId: parsed[ 0 ].id } );
					}
				}
			} catch {
				if ( ! aborted ) {
					setError(
						__( 'Could not load the icon sprite', 'icon-library' )
					);
				}
			}
		} )();
		return () => {
			aborted = true;
		};
	}, [ symbolId, setAttributes, reload ] );

	const options = useMemo< SymbolOption[] >(
		() => symbols.map( ( s ) => ( { label: s.id, value: s.id } ) ),
		[ symbols ]
	);

	const groups = useMemo( () => {
		const map = new Map< string, SpriteSymbol[] >();
		symbols.forEach( ( s ) => {
			const parts = s.id.split( '--' );
			const group = parts.length > 1 ? parts[ 0 ] : '';
			if ( ! map.has( group ) ) {
				map.set( group, [] );
			}
			map.get( group )!.push( s );
		} );
		return map;
	}, [ symbols ] );

	const groupOptions = useMemo< SymbolOption[] >( () => {
		const opts: SymbolOption[] = [
			{ value: 'all', label: __( 'All', 'icon-library' ) },
		];
		[ ...groups.keys() ]
			.filter( Boolean )
			.sort()
			.forEach( ( g ) => {
				opts.push( { value: g, label: g } );
			} );
		return opts;
	}, [ groups ] );

	const [ activeGroup, setActiveGroup ] = useState( 'all' );
	const [ view, setView ] = useState< 'grid' | 'list' >( 'grid' );

	const filteredOptions = useMemo< SymbolOption[] >( () => {
		if ( activeGroup === 'all' ) {
			return options;
		}
		return options.filter( ( o ) =>
			o.value.startsWith( activeGroup + '--' )
		);
	}, [ options, activeGroup ] );

	// rel is computed inline on the anchor to ensure noreferrer/noopener when target is _blank

	// Keep rel synced when toggling target
	useEffect( () => {
		const parts = new Set( ( rel || '' ).split( /\s+/ ).filter( Boolean ) );
		const before = Array.from( parts ).join( ' ' );
		if ( opensInNewTab ) {
			parts.add( 'noreferrer' );
			parts.add( 'noopener' );
		} else {
			parts.delete( 'noreferrer' );
			parts.delete( 'noopener' );
		}
		const after = Array.from( parts ).join( ' ' );
		if ( before !== after ) {
			setAttributes( { rel: after } );
		}
	}, [ opensInNewTab, rel, setAttributes ] );

	const dimensionWidth = style?.dimensions?.width ?? '';
	const resolvedWidth = dimensionWidth
		? resolveDimensionValue( dimensionWidth, presetSizes )
		: '';
	const svgWidth = resolvedWidth || width || '48px';
	const svgHeight = height || svgWidth;

	const svgStyle: CSSProperties = {
		width: svgWidth,
		height: svgHeight,
	};

	const svgClasses = [ 'svg-icon__svg' ];
	const textColorAttr = style?.color?.text ?? textColor ?? '';
	const backgroundColorAttr =
		style?.color?.background ?? backgroundColor ?? '';

	if ( textColorAttr ) {
		svgStyle.color = resolveColorValue( textColorAttr );
		svgClasses.push( 'has-text-color' );
	}
	if ( backgroundColorAttr ) {
		svgStyle.backgroundColor = resolveColorValue( backgroundColorAttr );
		svgClasses.push( 'has-background' );
	}

	const padding = style?.spacing?.padding;
	if ( padding ) {
		const spacingSides = [
			[ 'top', 'paddingTop' ],
			[ 'right', 'paddingRight' ],
			[ 'bottom', 'paddingBottom' ],
			[ 'left', 'paddingLeft' ],
		] as const;
		for ( const [ side, cssProp ] of spacingSides ) {
			const value = padding[ side ];
			if ( value ) {
				svgStyle[ cssProp ] = resolveSpacingValue( value );
			}
		}
	}

	const svgProps: SVGProps< SVGSVGElement > = {
		focusable: 'false',
		className: svgClasses.join( ' ' ),
		style: svgStyle,
	};
	if ( url ) {
		svgProps[ 'aria-hidden' ] = 'true';
	} else if ( label ) {
		svgProps[ 'aria-label' ] = label;
	} else {
		svgProps[ 'aria-hidden' ] = 'true';
	}

	const svgEl = (
		<svg { ...svgProps }>
			{ symbolId ? (
				<use href={ `${ SPRITE_URL }#${ symbolId }` } />
			) : null }
		</svg>
	);

	const { selectBlock } = useDispatch( 'core/block-editor' );
	const blockProps = useBlockProps( {
		className: 'svg-icon',
		onClick: () => selectBlock( clientId ),
	} );

	return (
		<>
			<BlockControls group="inline">
				<ToolbarGroup>
					<ToolbarButton
						onClick={ () => {
							setReload( ( t ) => t + 1 );
							setIsIconPickerOpen( true );
						} }
					>
						{ __( 'Replace', 'icon-library' ) }
					</ToolbarButton>
					<ToolbarButton
						icon="admin-links"
						label={ __( 'Insert/edit link', 'icon-library' ) }
						onClick={ () => setIsLinkPickerOpen( ( v ) => ! v ) }
					/>
					{ url ? (
						<ToolbarButton
							icon="editor-unlink"
							label={ __( 'Remove link', 'icon-library' ) }
							onClick={ () => setAttributes( { url: '' } ) }
						/>
					) : null }
				</ToolbarGroup>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'SVG', 'icon-library' ) }>
					<TextControl
						label={ __(
							'Aria label (for screen readers, when not linked)',
							'icon-library'
						) }
						value={ label || '' }
						onChange={ ( val ) =>
							setAttributes( { label: val || '' } )
						}
						help={ __(
							'Sets the aria-label on the SVG. For links the aria-label is set on the <a>.',
							'icon-library'
						) }
					/>
				</PanelBody>
				{ /* Colors + Dimensions: standard Gutenberg panels via supports.color / supports.dimensions */ }
			</InspectorControls>

			{ isIconPickerOpen && (
				<Modal
					title={ __( 'Select symbol', 'icon-library' ) }
					onRequestClose={ () => setIsIconPickerOpen( false ) }
					className="svg-icon__modal"
					size="large"
				>
					<div className="svg-icon__picker svg-icon__picker--modal">
						<div className="svg-icon__picker-toolbar">
							{ groupOptions.length > 1 && (
								<SelectControl
									className="svg-icon__group-select"
									value={ activeGroup }
									options={ groupOptions }
									onChange={ setActiveGroup }
								/>
							) }
							<ToggleGroupControl
								label={ __( 'View', 'icon-library' ) }
								value={ view }
								hideLabelFromVision
								onChange={ ( value ) =>
									setView(
										value === 'list' ? 'list' : 'grid'
									)
								}
							>
								<ToggleGroupControlOptionIcon
									value="grid"
									label={ __( 'Grid', 'icon-library' ) }
									icon={
										<span className="dashicons dashicons-grid-view" />
									}
								/>
								<ToggleGroupControlOptionIcon
									value="list"
									label={ __( 'List', 'icon-library' ) }
									icon={
										<span className="dashicons dashicons-list-view" />
									}
								/>
							</ToggleGroupControl>
						</div>
						<div
							className={ `svg-icon__picker-list svg-icon__picker-list--${ view }` }
						>
							{ filteredOptions.map( ( opt ) => {
								if ( view === 'grid' ) {
									return (
										<button
											key={ opt.value }
											type="button"
											className="svg-icon__picker-item svg-icon__picker-item--grid"
											data-tip={ opt.label }
											onClick={ () => {
												setAttributes( {
													symbolId: opt.value,
												} );
												setIsIconPickerOpen( false );
											} }
										>
											<svg
												className="svg-icon__picker-icon"
												aria-hidden="true"
											>
												{ opt.value ? (
													<use
														href={ `${ SPRITE_URL }#${ opt.value }` }
													/>
												) : null }
											</svg>
											<span className="screen-reader-text">
												{ opt.label }
											</span>
										</button>
									);
								}
								return (
									<button
										key={ opt.value }
										type="button"
										className="svg-icon__picker-item"
										onClick={ () => {
											setAttributes( {
												symbolId: opt.value,
											} );
											setIsIconPickerOpen( false );
										} }
									>
										<svg
											className="svg-icon__picker-icon"
											aria-hidden="true"
										>
											{ opt.value ? (
												<use
													href={ `${ SPRITE_URL }#${ opt.value }` }
												/>
											) : null }
										</svg>
										<span className="svg-icon__picker-label">
											{ opt.label }
										</span>
									</button>
								);
							} ) }
						</div>
					</div>
				</Modal>
			) }

			{ isLinkPickerOpen && (
				<Popover
					position="bottom center"
					onClose={ () => setIsLinkPickerOpen( false ) }
				>
					<div style={ { width: 360 } }>
						<LinkControl
							value={ {
								url: url || '',
								opensInNewTab: !! opensInNewTab,
							} }
							onChange={ ( next ) => {
								setAttributes( {
									url: next.url || '',
									opensInNewTab: !! next.opensInNewTab,
								} );
							} }
							settings={ [ 'opensInNewTab' ] }
						/>
					</div>
				</Popover>
			) }

			<div { ...blockProps }>
				{ error ? (
					<div className="svg-icon__error">{ error }</div>
				) : null }
				{ symbolId ? (
					<div className="svg-icon__preview" data-symbol={ symbolId }>
						{ ( () => {
							let content = svgEl;
							if ( url ) {
								if ( opensInNewTab ) {
									content = (
										<a
											href={ url }
											target="_blank"
											rel="noreferrer noopener"
											aria-label={ label || undefined }
											onClick={ ( e ) => {
												e.preventDefault();
											} }
										>
											{ svgEl }
										</a>
									);
								} else {
									content = (
										<a
											href={ url }
											rel={
												( rel || '' ).trim() ||
												undefined
											}
											aria-label={ label || undefined }
											onClick={ ( e ) => {
												e.preventDefault();
											} }
										>
											{ svgEl }
										</a>
									);
								}
							}
							return content;
						} )() }
					</div>
				) : (
					<div className="svg-icon__placeholder">
						{ __( 'Select symbol …', 'icon-library' ) }
					</div>
				) }
			</div>
		</>
	);
}

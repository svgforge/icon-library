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
	ColorPalette,
	Modal,
	ToolbarGroup,
	ToolbarButton,
	Popover,
	SelectControl,
	TextControl,
	Button,
	ButtonGroup,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { CSSProperties, SVGProps } from 'react';
import {
	isDimensionPresetActive,
	parseLength,
	toDimensionPresetEntries,
	UNIT_OPTIONS,
} from './icon-sizes';
import { parseSymbols, type SpriteSymbol } from './icon-colors';

export type IconLibraryAttributes = {
	symbolId: string;
	url: string;
	opensInNewTab: boolean;
	rel: string;
	label: string;
	fillColor: string;
	strokeColor: string;
	width: string;
	height: string;
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
		fillColor,
		strokeColor,
		width,
		height,
	} = attributes;
	const [ symbols, setSymbols ] = useState< SpriteSymbol[] >( [] );
	const [ error, setError ] = useState( '' );
	const [ isLinkPickerOpen, setIsLinkPickerOpen ] = useState( false );
	const [ isIconPickerOpen, setIsIconPickerOpen ] = useState( false );
	const [ reload, setReload ] = useState( 0 );

	const colorCustom = useSetting( 'color.custom' );
	const colorPalette = useSetting( 'color.palette' );
	const colorsEnabled =
		colorCustom !== false &&
		Array.isArray( colorPalette ) &&
		colorPalette.length > 0;
	const dimensionSizes = useSetting( 'dimensions.dimensionSizes' );
	const widthSetting = useSetting( 'dimensions.width' );
	const heightSetting = useSetting( 'dimensions.height' );
	const selectedSymbol = symbols.find( ( s ) => s.id === symbolId );
	const showColorPanel = ! ( selectedSymbol?.colored ?? false );

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

	const svgStyle: CSSProperties = {};
	if ( fillColor ) {
		svgStyle.fill = fillColor;
	}
	if ( strokeColor ) {
		svgStyle.stroke = strokeColor;
	}
	if ( width ) {
		svgStyle.width = width;
	}
	if ( height ) {
		svgStyle.height = height;
	}

	const svgProps: SVGProps< SVGSVGElement > = {
		focusable: 'false',
		className: 'svg-fragment__svg',
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

	const { num: widthNum, unit: widthUnit } = parseLength( width, 'px' );
	const { num: heightNum, unit: heightUnit } = parseLength( height, 'px' );

	const dimensionPresetEntries = toDimensionPresetEntries( dimensionSizes );

	const customWidthEnabled = widthSetting !== false;
	const customHeightEnabled = heightSetting !== false;
	const showSizePanel =
		dimensionPresetEntries.length > 0 ||
		customWidthEnabled ||
		customHeightEnabled;

	const { selectBlock } = useDispatch( 'core/block-editor' );
	const blockProps = useBlockProps( {
		className: 'svg-fragment',
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
				{ /* Link settings moved to toolbar LinkControl */ }
				{ colorsEnabled && (
					<PanelBody
						title={ __( 'Colors', 'icon-library' ) }
						initialOpen={ false }
					>
						{ showColorPanel ? (
							<>
								<p className="svg-fragment__label">
									{ __(
										'Fill color (fill)',
										'icon-library'
									) }
								</p>
								<ColorPalette
									value={ fillColor || '' }
									onChange={ ( value ) =>
										setAttributes( {
											fillColor: value || '',
										} )
									}
								/>
								<p className="svg-fragment__label">
									{ __(
										'Stroke color (stroke)',
										'icon-library'
									) }
								</p>
								<ColorPalette
									value={ strokeColor || '' }
									onChange={ ( value ) =>
										setAttributes( {
											strokeColor: value || '',
										} )
									}
								/>
							</>
						) : (
							<p className="svg-fragment__label">
								{ __(
									'This icon already contains its own colors and cannot be recolored.',
									'icon-library'
								) }
							</p>
						) }
					</PanelBody>
				) }
				{ showSizePanel && (
					<PanelBody
						title={ __( 'Size', 'icon-library' ) }
						initialOpen={ false }
					>
						{ dimensionPresetEntries.length > 0 && (
							<>
								<p className="svg-fragment__label">
									{ __( 'Preset sizes', 'icon-library' ) }
								</p>
								<ButtonGroup className="svg-fragment__size-presets">
									{ dimensionPresetEntries.map(
										( preset ) => (
											<Button
												key={
													preset.slug ?? preset.size
												}
												variant="secondary"
												isPressed={ isDimensionPresetActive(
													width,
													height,
													preset.size
												) }
												onClick={ () =>
													setAttributes( {
														width: preset.size,
														height: preset.size,
													} )
												}
											>
												{ preset.name || preset.size }
											</Button>
										)
									) }
								</ButtonGroup>
							</>
						) }
						{ ( customWidthEnabled || customHeightEnabled ) && (
							<div
								style={ {
									display: 'grid',
									gridTemplateColumns: '1fr 88px',
									gap: 8,
								} }
							>
								{ customWidthEnabled && (
									<TextControl
										label={ __( 'Width', 'icon-library' ) }
										value={ widthNum }
										onChange={ ( val ) => {
											const num = val.replace(
												/[^0-9.]/g,
												''
											);
											setAttributes( {
												width:
													( num || '48' ) +
													( widthUnit || 'px' ),
											} );
										} }
									/>
								) }
								{ customWidthEnabled && (
									<SelectControl
										label={ __( 'Unit', 'icon-library' ) }
										value={ widthUnit }
										options={ UNIT_OPTIONS }
										onChange={ ( unit ) => {
											setAttributes( {
												width:
													( widthNum || '48' ) + unit,
											} );
										} }
									/>
								) }
								{ customHeightEnabled && (
									<TextControl
										label={ __( 'Height', 'icon-library' ) }
										value={ heightNum }
										onChange={ ( val ) => {
											const num = val.replace(
												/[^0-9.]/g,
												''
											);
											setAttributes( {
												height:
													( num || '48' ) +
													( heightUnit || 'px' ),
											} );
										} }
									/>
								) }
								{ customHeightEnabled && (
									<SelectControl
										label={ __( 'Unit', 'icon-library' ) }
										value={ heightUnit }
										options={ UNIT_OPTIONS }
										onChange={ ( unit ) => {
											setAttributes( {
												height:
													( heightNum || '48' ) +
													unit,
											} );
										} }
									/>
								) }
							</div>
						) }
					</PanelBody>
				) }
			</InspectorControls>

			{ isIconPickerOpen && (
				<Modal
					title={ __( 'Select symbol', 'icon-library' ) }
					onRequestClose={ () => setIsIconPickerOpen( false ) }
					className="svg-fragment__modal"
					size="large"
				>
					<div className="svg-fragment__picker svg-fragment__picker--modal">
						<div className="svg-fragment__picker-toolbar">
							{ groupOptions.length > 1 && (
								<SelectControl
									className="svg-fragment__group-select"
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
							className={ `svg-fragment__picker-list svg-fragment__picker-list--${ view }` }
						>
							{ filteredOptions.map( ( opt ) => {
								if ( view === 'grid' ) {
									return (
										<button
											key={ opt.value }
											type="button"
											className="svg-fragment__picker-item svg-fragment__picker-item--grid"
											data-tip={ opt.label }
											onClick={ () => {
												setAttributes( {
													symbolId: opt.value,
												} );
												setIsIconPickerOpen( false );
											} }
										>
											<svg
												className="svg-fragment__picker-icon"
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
										className="svg-fragment__picker-item"
										onClick={ () => {
											setAttributes( {
												symbolId: opt.value,
											} );
											setIsIconPickerOpen( false );
										} }
									>
										<svg
											className="svg-fragment__picker-icon"
											aria-hidden="true"
										>
											{ opt.value ? (
												<use
													href={ `${ SPRITE_URL }#${ opt.value }` }
												/>
											) : null }
										</svg>
										<span className="svg-fragment__picker-label">
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
					<div className="svg-fragment__error">{ error }</div>
				) : null }
				{ symbolId ? (
					<div
						className="svg-fragment__preview"
						data-symbol={ symbolId }
					>
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
					<div className="svg-fragment__placeholder">
						{ __( 'Select symbol …', 'icon-library' ) }
					</div>
				) }
			</div>
		</>
	);
}

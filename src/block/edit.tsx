import { useEffect, useMemo, useState } from '@wordpress/element';
import './editor.css';
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	BlockControls,
	useBlockProps,
	LinkControl,
} from '@wordpress/block-editor';
import { useDispatch } from '@wordpress/data';
import {
	PanelBody,
	ColorPalette,
	Dropdown,
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

export type IconizerAttributes = {
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

interface SpriteSymbol {
	id: string;
	viewBox: string | null;
}

const SPRITE_URL = window.wpIconizerSettings?.spriteUrl || '/ico.svg';

const parseSymbols = ( svgText: string ): SpriteSymbol[] => {
	const parser = new window.DOMParser();
	const doc = parser.parseFromString( svgText, 'image/svg+xml' );
	const symbols = Array.from( doc.getElementsByTagName( 'symbol' ) );
	return symbols
		.map( ( s ) => ( {
			id: s.getAttribute( 'id' ),
			viewBox: s.getAttribute( 'viewBox' ),
		} ) )
		.filter( ( s ): s is SpriteSymbol => !! s.id );
};

export default function Edit( {
	attributes,
	setAttributes,
	clientId,
}: BlockEditProps< IconizerAttributes > ) {
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
	const [ reload, setReload ] = useState( 0 );

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
						__( 'Could not load the icon sprite', 'wp-iconizer' )
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
			{ value: 'all', label: __( 'All', 'wp-iconizer' ) },
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

	const parseLength = (
		value: string,
		fallbackUnit = 'px'
	): { num: string; unit: string } => {
		if ( ! value ) {
			return { num: '', unit: fallbackUnit };
		}
		const match = String( value ).match(
			/^(\d+(?:\.\d+)?)(px|em|rem|%)?$/
		);
		if ( ! match ) {
			return { num: '', unit: fallbackUnit };
		}
		return { num: match[ 1 ], unit: match[ 2 ] || fallbackUnit };
	};

	const { num: widthNum, unit: widthUnit } = parseLength( width, 'px' );
	const { num: heightNum, unit: heightUnit } = parseLength( height, 'px' );

	const unitOptions: Array< { value: string; label: string } > = [
		{ value: 'px', label: 'px' },
		{ value: 'em', label: 'em' },
		{ value: 'rem', label: 'rem' },
		{ value: '%', label: '%' },
	];

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
						icon="admin-links"
						label={ __( 'Insert/edit link', 'wp-iconizer' ) }
						onClick={ () => setIsLinkPickerOpen( ( v ) => ! v ) }
					/>
					{ url ? (
						<ToolbarButton
							icon="editor-unlink"
							label={ __( 'Remove link', 'wp-iconizer' ) }
							onClick={ () => setAttributes( { url: '' } ) }
						/>
					) : null }
				</ToolbarGroup>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'SVG', 'wp-iconizer' ) }>
					<TextControl
						label={ __(
							'Aria label (for screen readers, when not linked)',
							'wp-iconizer'
						) }
						value={ label || '' }
						onChange={ ( val ) =>
							setAttributes( { label: val || '' } )
						}
						help={ __(
							'Sets the aria-label on the SVG. For links the aria-label is set on the <a>.',
							'wp-iconizer'
						) }
					/>
					<Dropdown
						renderToggle={ ( { isOpen, onToggle } ) => (
							<ToolbarButton
								onClick={ () => {
									if ( ! isOpen ) {
										setReload( ( t ) => t + 1 );
									}
									onToggle();
								} }
								aria-expanded={ isOpen }
								className="svg-fragment__toggle"
							>
								{ symbolId ? (
									<svg
										className="svg-fragment__toggle-icon"
										aria-hidden="true"
									>
										<use
											href={ `${ SPRITE_URL }#${ symbolId }` }
										/>
									</svg>
								) : null }
								<span className="svg-fragment__toggle-label">
									{ symbolId ||
										__( 'Select symbol …', 'wp-iconizer' ) }
								</span>
							</ToolbarButton>
						) }
						renderContent={ () => (
							<div className="svg-fragment__picker">
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
										label={ __( 'View', 'wp-iconizer' ) }
										value={ view }
										hideLabelFromVision
										onChange={ ( value ) =>
											setView(
												value === 'list'
													? 'list'
													: 'grid'
											)
										}
									>
										<ToggleGroupControlOptionIcon
											value="grid"
											label={ __(
												'Grid',
												'wp-iconizer'
											) }
											icon={
												<span className="dashicons dashicons-grid-view" />
											}
										/>
										<ToggleGroupControlOptionIcon
											value="list"
											label={ __(
												'List',
												'wp-iconizer'
											) }
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
													onClick={ () =>
														setAttributes( {
															symbolId: opt.value,
														} )
													}
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
												onClick={ () =>
													setAttributes( {
														symbolId: opt.value,
													} )
												}
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
						) }
					/>
				</PanelBody>
				{ /* Link settings moved to toolbar LinkControl */ }
				<PanelBody
					title={ __( 'Colors', 'wp-iconizer' ) }
					initialOpen={ false }
				>
					<p className="svg-fragment__label">
						{ __( 'Fill color (fill)', 'wp-iconizer' ) }
					</p>
					<ColorPalette
						value={ fillColor || '' }
						onChange={ ( value ) =>
							setAttributes( { fillColor: value || '' } )
						}
					/>
					<p className="svg-fragment__label">
						{ __( 'Stroke color (stroke)', 'wp-iconizer' ) }
					</p>
					<ColorPalette
						value={ strokeColor || '' }
						onChange={ ( value ) =>
							setAttributes( { strokeColor: value || '' } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Size', 'wp-iconizer' ) }
					initialOpen={ false }
				>
					<div
						style={ {
							display: 'grid',
							gridTemplateColumns: '1fr 88px',
							gap: 8,
						} }
					>
						<TextControl
							label={ __( 'Width', 'wp-iconizer' ) }
							value={ widthNum }
							onChange={ ( val ) => {
								const num = val.replace( /[^0-9.]/g, '' );
								setAttributes( {
									width:
										( num || '48' ) + ( widthUnit || 'px' ),
								} );
							} }
						/>
						<SelectControl
							label={ __( 'Unit', 'wp-iconizer' ) }
							value={ widthUnit }
							options={ unitOptions }
							onChange={ ( unit ) => {
								setAttributes( {
									width: ( widthNum || '48' ) + unit,
								} );
							} }
						/>
						<TextControl
							label={ __( 'Height', 'wp-iconizer' ) }
							value={ heightNum }
							onChange={ ( val ) => {
								const num = val.replace( /[^0-9.]/g, '' );
								setAttributes( {
									height:
										( num || '48' ) +
										( heightUnit || 'px' ),
								} );
							} }
						/>
						<SelectControl
							label={ __( 'Unit', 'wp-iconizer' ) }
							value={ heightUnit }
							options={ unitOptions }
							onChange={ ( unit ) => {
								setAttributes( {
									height: ( heightNum || '48' ) + unit,
								} );
							} }
						/>
					</div>
				</PanelBody>
			</InspectorControls>

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
						{ __( 'Select symbol …', 'wp-iconizer' ) }
					</div>
				) }
			</div>
		</>
	);
}

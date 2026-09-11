declare module '@wordpress/block-editor' {
	export function useBlockProps(
		opts?: Record< string, unknown >
	): Record< string, unknown >;

	export const BlockControls: ( props: {
		group?: string;
		children?: React.ReactNode;
	} ) => JSX.Element;

	export const InspectorControls: ( props: {
		children?: React.ReactNode;
	} ) => JSX.Element;

	export const LinkControl: ( props: {
		value?: { url?: string; opensInNewTab?: boolean };
		onChange?: ( value: { url: string; opensInNewTab?: boolean } ) => void;
		settings?: string[];
	} ) => JSX.Element;
}

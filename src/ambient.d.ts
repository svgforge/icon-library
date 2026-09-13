declare module '*.css';

declare module '*.svg' {
	export const ReactComponent: React.FunctionComponent<
		React.SVGProps< SVGSVGElement > & { title?: string }
	>;

	const src: string;
	export default src;
}

declare module '@wordpress/block-editor' {
	export function useSetting(
		path: string
	): string | boolean | unknown[] | null;
	export function useSettings( ...paths: string[] ): unknown[];
}

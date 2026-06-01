/**
 * Public widget bundle. Exposed on window.batchPilot.widgets so Pro / third-party
 * plugins can reuse the same FilterRow renderer without copy-pasting it.
 *
 * Stability: treat the exported shape as a public API. Changes here are
 * breaking changes for downstream consumers and should be versioned.
 */
import FilterRow from './components/FilterRow';

const ns = ( window.batchPilot = window.batchPilot || {} );
ns.widgets = {
	FilterRow,
};

export { FilterRow };

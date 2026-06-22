import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Dashboard from './Dashboard';

// Isolate the page from the real layout (which needs an Inertia app context).
vi.mock('../../Layouts/AppLayout', () => ({
    default: ({ children }) => <div data-testid="layout">{children}</div>,
}));

const NODES = [
    { id: 'actor:1', label: 'Hub', type: 'military', degree: 1.0, betweenness: 0.333, pagerank: 0.21 },
    { id: 'actor:2', label: 'Spoke', type: 'org', degree: 0.5, betweenness: 0.0, pagerank: 0.14 },
];

function mockFetch(metric = 'degree') {
    return vi.fn(() =>
        Promise.resolve({
            ok: true,
            json: async () => ({ metric, count: NODES.length, nodes: NODES }),
        }),
    );
}

beforeEach(() => {
    vi.stubGlobal('fetch', mockFetch());
});

describe('Influence Dashboard', () => {
    it('renders the ranked actors once data loads', async () => {
        render(<Dashboard />);

        expect(await screen.findByText('Hub')).toBeInTheDocument();
        expect(screen.getByText('Spoke')).toBeInTheDocument();
        expect(screen.getByText('1.000')).toBeInTheDocument(); // hub degree
    });

    it('offers all three metric switches', async () => {
        render(<Dashboard />);
        await screen.findByText('Hub');

        expect(screen.getByRole('button', { name: 'Degree' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Betweenness' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'PageRank' })).toBeInTheDocument();
    });

    it('refetches with the selected metric when switched', async () => {
        render(<Dashboard />);
        await screen.findByText('Hub');

        fireEvent.click(screen.getByRole('button', { name: 'Betweenness' }));

        expect(fetch).toHaveBeenLastCalledWith(
            expect.stringContaining('metric=betweenness'),
            expect.anything(),
        );
        await screen.findByText('Hub'); // flush the re-fetch state update
    });

    it('starts in a loading state before data arrives', async () => {
        render(<Dashboard />);

        // The ranked rows are not present yet on the first synchronous render.
        expect(screen.queryByText('Hub')).not.toBeInTheDocument();

        await screen.findByText('Hub'); // flush the pending fetch before teardown
    });
});

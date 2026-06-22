import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Compare from './Compare';

vi.mock('../../Layouts/AppLayout', () => ({
    default: ({ children }) => <div data-testid="layout">{children}</div>,
}));

const NODES = [
    { id: 'actor:1', label: 'Hub', type: 'military', degree: 1.0, betweenness: 0.333, pagerank: 0.21 },
    { id: 'actor:2', label: 'Spoke', type: 'org', degree: 0.5, betweenness: 0.0, pagerank: 0.14 },
];

beforeEach(() => {
    vi.stubGlobal(
        'fetch',
        vi.fn(() =>
            Promise.resolve({
                ok: true,
                json: async () => ({ metric: 'all', count: NODES.length, nodes: NODES }),
            }),
        ),
    );
});

describe('Influence Compare', () => {
    it('renders a row per metric once data loads', async () => {
        render(<Compare />);

        expect(await screen.findByText('Degree')).toBeInTheDocument();
        expect(screen.getByText('Betweenness')).toBeInTheDocument();
        expect(screen.getByText('PageRank')).toBeInTheDocument();
    });

    it('fetches all metrics in a single call', async () => {
        render(<Compare />);
        await screen.findByText('Degree');

        expect(fetch).toHaveBeenCalledWith(
            expect.stringContaining('metric=all'),
            expect.anything(),
        );
    });

    it('renders two actor selectors populated from the data', async () => {
        render(<Compare />);
        await screen.findByText('Degree');

        const selects = screen.getAllByRole('combobox');
        expect(selects).toHaveLength(2);
        // Each select lists both actors as options (2 selects × 2 options).
        expect(screen.getAllByRole('option', { name: 'Hub' })).toHaveLength(2);
    });

    it('defaults to the first two actors and shows their scores', async () => {
        render(<Compare />);
        await screen.findByText('Degree');

        expect(screen.getByText('1.000')).toBeInTheDocument(); // Hub degree (Actor A)
        expect(screen.getByText('0.500')).toBeInTheDocument(); // Spoke degree (Actor B)
    });

    it('updates the comparison when a different actor is selected', async () => {
        render(<Compare />);
        await screen.findByText('Degree');

        // Point Actor B at the hub too → both columns now show degree 1.000.
        fireEvent.change(screen.getAllByRole('combobox')[1], { target: { value: 'actor:1' } });

        expect(screen.getAllByText('1.000')).toHaveLength(2);
    });
});

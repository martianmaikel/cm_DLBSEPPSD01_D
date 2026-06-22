// Shared metadata for the three centrality measures, used by the influence
// dashboard and the actor-comparison view.
export const METRICS = [
    {
        key: 'degree',
        label: 'Degree',
        blurb: 'Direct connections — how many other actors this one is directly linked to.',
    },
    {
        key: 'betweenness',
        label: 'Betweenness',
        blurb: 'Bridge role — how often this actor lies on the shortest path between two others.',
    },
    {
        key: 'pagerank',
        label: 'PageRank',
        blurb: 'Influence by association — importance weighted by the importance of connected actors.',
    },
];

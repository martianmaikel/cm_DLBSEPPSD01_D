import '@testing-library/jest-dom';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// Unmount React trees between tests so they stay isolated.
afterEach(() => cleanup());

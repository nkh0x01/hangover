import { bogProvider, mockProvider } from './bog';
import type { PaymentProvider } from './types';

export type ProviderName = 'bog' | 'tbc' | 'stripe' | 'mock';

// Pick a provider per env. Defaults to "mock" so the flow runs end-to-end
// without any external creds.
export function getProvider(): PaymentProvider {
  const wanted = (process.env.PAYMENTS_PROVIDER ?? 'mock') as ProviderName;
  switch (wanted) {
    case 'bog':
      if (!process.env.BOG_CLIENT_ID || !process.env.BOG_CLIENT_SECRET) {
        console.warn('[payments] PAYMENTS_PROVIDER=bog but no BOG creds — falling back to mock');
        return mockProvider;
      }
      return bogProvider;
    case 'mock':
    default:
      return mockProvider;
  }
}

export { bogProvider, mockProvider };
export * from './types';
export * from './store';

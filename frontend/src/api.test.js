import { api } from './api';

beforeEach(() => {
  localStorage.clear();
  global.fetch = jest.fn();
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe('api client', () => {
  test('GET adds Authorization header when token exists', async () => {
    localStorage.setItem('token', 'test-jwt');
    fetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: () => Promise.resolve({ data: { vendors: [] } }),
    });

    await api.get('/vendors');

    expect(fetch).toHaveBeenCalledWith('/api/vendors', expect.objectContaining({
      headers: expect.objectContaining({
        Authorization: 'Bearer test-jwt',
      }),
    }));
  });

  test('GET does not add Authorization when no token', async () => {
    fetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: () => Promise.resolve({ data: {} }),
    });

    await api.get('/health');

    const headers = fetch.mock.calls[0][1].headers;
    expect(headers.Authorization).toBeUndefined();
  });

  test('POST sends JSON body with Content-Type', async () => {
    fetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: () => Promise.resolve({ data: { token: 'abc' } }),
    });

    await api.post('/auth/login', { username: 'admin', password: 'pass' });

    expect(fetch).toHaveBeenCalledWith('/api/auth/login', expect.objectContaining({
      method: 'POST',
      headers: expect.objectContaining({
        'Content-Type': 'application/json',
      }),
      body: JSON.stringify({ username: 'admin', password: 'pass' }),
    }));
  });

  test('unwraps data envelope from response', async () => {
    fetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: () => Promise.resolve({ success: true, data: { vendors: [{ id: 1 }] } }),
    });

    const result = await api.get('/vendors');
    expect(result).toEqual({ vendors: [{ id: 1 }] });
  });

  test('401 clears token and user from localStorage', async () => {
    localStorage.setItem('token', 'expired');
    localStorage.setItem('user', '{}');

    fetch.mockResolvedValue({ ok: false, status: 401 });

    await expect(api.get('/vendors')).rejects.toThrow('Unauthorized');
    expect(localStorage.getItem('token')).toBeNull();
    expect(localStorage.getItem('user')).toBeNull();
  });

  test('non-401 error throws with message from response', async () => {
    fetch.mockResolvedValue({
      ok: false,
      status: 422,
      json: () => Promise.resolve({ error: 'Validation failed' }),
    });

    await expect(api.post('/vendors', {})).rejects.toThrow('Validation failed');
  });

  test('postForm does not set Content-Type (let browser set multipart boundary)', async () => {
    fetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: () => Promise.resolve({ data: { id: 1 } }),
    });

    const formData = new FormData();
    formData.append('file', new Blob(['pdf']), 'test.pdf');

    await api.postForm('/invoices/upload', formData);

    const headers = fetch.mock.calls[0][1].headers;
    expect(headers['Content-Type']).toBeUndefined();
  });

  test('204 response returns null', async () => {
    fetch.mockResolvedValue({ ok: true, status: 204 });

    const result = await api.delete('/vendors/1');
    expect(result).toBeNull();
  });
});

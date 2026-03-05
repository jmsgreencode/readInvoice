module.exports = {
  testEnvironment: 'jsdom',
  moduleNameMapper: {
    '\\.css$': '<rootDir>/src/__mocks__/styleMock.js',
  },
  transform: {
    '^.+\\.jsx?$': 'babel-jest',
  },
  testMatch: ['<rootDir>/src/**/*.test.js'],
};

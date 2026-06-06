//
//  StoreViews.swift
//  ElectricSim
//
//  მონეტიზაციის ინტერფეისი: სარეკლამო ბანერი, Pro-ს paywall, „შესახებ" ეკრანი.
//  მთლიანად ქართულად. ბრენდი: Gadget Georgia. დაფინანსება: Tsili.Ge.
//

import SwiftUI

// MARK: - Ad banner (house ad; AdMob-ით ჩანაცვლებადი)

struct AdBannerView: View {
    @EnvironmentObject var store: StoreManager
    @EnvironmentObject var ads: AdManager

    var body: some View {
        if store.isPro {
            EmptyView() // Pro მომხმარებლებს რეკლამა არ ეჩვენებათ
        } else if let ad = ads.current {
            Link(destination: ad.url ?? URL(string: "https://gadget.com.ge")!) {
                HStack(spacing: 12) {
                    Image(systemName: ad.symbol)
                        .font(.title3)
                        .foregroundStyle(.yellow)
                        .frame(width: 36, height: 36)
                        .background(Color.black.opacity(0.05), in: RoundedRectangle(cornerRadius: 8))
                    VStack(alignment: .leading, spacing: 1) {
                        Text(ad.title).font(.caption.bold()).foregroundStyle(.primary)
                        Text(ad.subtitle).font(.caption2).foregroundStyle(.secondary).lineLimit(1)
                    }
                    Spacer()
                    Text(ad.cta)
                        .font(.caption2.bold())
                        .padding(.horizontal, 10).padding(.vertical, 5)
                        .background(Color.yellow, in: Capsule())
                        .foregroundStyle(.black)
                }
                .padding(.horizontal, 12).padding(.vertical, 8)
                .background(Color(.secondarySystemBackground))
                .overlay(alignment: .topLeading) {
                    Text("რეკლამა")
                        .font(.system(size: 8))
                        .foregroundStyle(.secondary)
                        .padding(2)
                }
            }
            .buttonStyle(.plain)
        }
    }
}

// MARK: - Paywall

struct PaywallView: View {
    @EnvironmentObject var store: StoreManager
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 20) {
                    Image(systemName: "bolt.shield.fill")
                        .font(.system(size: 56))
                        .foregroundStyle(.yellow)
                        .padding(.top, 12)

                    Text("ელექტრიკი Pro")
                        .font(.title.bold())
                    Text("განბლოკე სრული კურსი და მოიშორე რეკლამა")
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                        .multilineTextAlignment(.center)

                    VStack(alignment: .leading, spacing: 14) {
                        benefit("bolt.fill", "3 ფაზის მოდული", "4-პოლუსიანი ფარი, ფაზების ბალანსი, 3-ფაზიანი მოტორი")
                        benefit("rectangle.3.group.fill", "ყველა დონე", "სრული პროგრესია გახსნილი")
                        benefit("nosign", "რეკლამის გარეშე", "ყურადღების გადამტანის გარეშე სწავლა")
                        benefit("heart.fill", "დაუჭირე მხარი ქართულ პროექტს", "Gadget Georgia-ს განვითარება")
                    }
                    .padding()
                    .background(Color(.secondarySystemBackground), in: RoundedRectangle(cornerRadius: 16))

                    Button {
                        Task { await store.purchasePro() }
                    } label: {
                        HStack {
                            if store.purchaseInFlight { ProgressView().tint(.black) }
                            Text(store.isPro ? "უკვე გააქტიურებულია ✓"
                                 : "განბლოკვა \(store.proProduct != nil ? "— " + store.displayPrice : "")")
                                .bold()
                        }
                        .frame(maxWidth: .infinity)
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(.yellow)
                    .disabled(store.isPro || store.purchaseInFlight)

                    Button("შესყიდვების აღდგენა") {
                        Task { await store.restorePurchases() }
                    }
                    .font(.footnote)

                    if let err = store.lastError {
                        Text(err).font(.caption2).foregroundStyle(.red).multilineTextAlignment(.center)
                    }

                    Text("ერთჯერადი შესყიდვა. გადახდა App Store-ის ანგარიშიდან. ფასი storefront-ის მიხედვით.")
                        .font(.system(size: 10))
                        .foregroundStyle(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.top, 4)
                }
                .padding()
            }
            .navigationTitle("Pro")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("დახურვა") { dismiss() }
                }
            }
            .onChange(of: store.isPro) { pro in if pro { dismiss() } }
        }
    }

    private func benefit(_ symbol: String, _ title: String, _ subtitle: String) -> some View {
        HStack(alignment: .top, spacing: 12) {
            Image(systemName: symbol).foregroundStyle(.yellow).frame(width: 26)
            VStack(alignment: .leading, spacing: 2) {
                Text(title).font(.subheadline.bold())
                Text(subtitle).font(.caption).foregroundStyle(.secondary)
            }
        }
    }
}

// MARK: - About / Settings

struct AboutView: View {
    @EnvironmentObject var store: StoreManager
    @EnvironmentObject var game: GameState
    @Environment(\.dismiss) private var dismiss
    @State private var showPaywall = false

    private var appVersion: String {
        let v = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "1.0"
        let b = Bundle.main.infoDictionary?["CFBundleVersion"] as? String ?? "1"
        return "\(v) (\(b))"
    }

    var body: some View {
        NavigationStack {
            List {
                Section {
                    HStack(spacing: 14) {
                        Image(systemName: "bolt.fill")
                            .font(.largeTitle).foregroundStyle(.yellow)
                            .frame(width: 54, height: 54)
                            .background(Color(.secondarySystemBackground), in: RoundedRectangle(cornerRadius: 12))
                        VStack(alignment: .leading) {
                            Text("ელექტრიკის სიმულატორი").font(.headline)
                            Text("ვერსია \(appVersion)").font(.caption).foregroundStyle(.secondary)
                        }
                    }
                }

                Section("Pro") {
                    if store.isPro {
                        Label("Pro გააქტიურებულია — გმადლობთ!", systemImage: "checkmark.seal.fill")
                            .foregroundStyle(.green)
                    } else {
                        Button {
                            showPaywall = true
                        } label: {
                            Label("განბლოკე ელექტრიკი Pro", systemImage: "bolt.shield.fill")
                        }
                    }
                    Button("შესყიდვების აღდგენა") {
                        Task { await store.restorePurchases() }
                    }
                }

                Section("შემქმნელი") {
                    Link(destination: URL(string: "https://gadget.com.ge")!) {
                        Label("Gadget Georgia", systemImage: "globe")
                    }
                    Link(destination: URL(string: "mailto:info@gadget.com.ge")!) {
                        Label("info@gadget.com.ge", systemImage: "envelope")
                    }
                }

                Section {
                    Link(destination: URL(string: "https://tsili.ge")!) {
                        Label("დააფინანსე Tsili.Ge-ზე", systemImage: "heart.fill")
                            .foregroundStyle(.pink)
                    }
                } header: {
                    Text("მხარდაჭერა")
                } footer: {
                    Text("პროექტი ვითარდება Gadget Georgia-ს მიერ. დამატებითი დაფინანსების მოსაზიდად გამოიყენე Tsili.Ge — შენი წვლილი ეხმარება ახალი დონეებისა და ფუნქციების შექმნას.")
                }

                Section("სამართლებრივი") {
                    Link(destination: URL(string: "https://gadget.com.ge/privacy")!) {
                        Label("კონფიდენციალურობის პოლიტიკა", systemImage: "hand.raised.fill")
                    }
                }

                Section {
                    Button(role: .destructive) {
                        game.resetProgress()
                    } label: {
                        Label("პროგრესის განულება", systemImage: "arrow.counterclockwise")
                    }
                }
            }
            .navigationTitle("შესახებ")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .confirmationAction) {
                    Button("დახურვა") { dismiss() }
                }
            }
            .sheet(isPresented: $showPaywall) { PaywallView().environmentObject(store) }
        }
    }
}
